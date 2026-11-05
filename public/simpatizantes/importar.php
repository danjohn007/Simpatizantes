<?php
/**
 * Importación masiva de simpatizantes (Excel o CSV) en tres pasos:
 *   1. Subir el archivo y elegir la campaña.
 *   2. Revisar: el sistema valida cada fila y muestra qué se importará y qué no.
 *   3. Confirmar: se guardan solo las filas válidas; se puede descargar un reporte de lo omitido.
 *
 * Permisos: super admin, admin y coordinador (el coordinador solo importa a su campaña).
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Simpatizante.php';
require_once __DIR__ . '/../../app/models/Campana.php';
require_once __DIR__ . '/../../app/models/LogAuditoria.php';
require_once __DIR__ . '/../../app/services/ImportacionService.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin', 'coordinador']);

$base = BASE_URL . '/public/simpatizantes/importar.php';
$dir = defined('BASE_PATH') ? BASE_PATH . '/storage/importaciones' : __DIR__ . '/../../storage/importaciones';
$modelo = new Simpatizante();
$campanaModel = new Campana();

// ---------------------------------------------------------------- utilidades

function imp_limpiar_viejos($dir) {
    foreach (glob($dir . '/*') ?: [] as $f) {
        if (is_file($f) && filemtime($f) < time() - 86400) { @unlink($f); }
    }
}
function imp_ruta($dir, $token, $ext) {
    return $dir . '/' . $token . '.' . $ext;
}
function imp_token_valido($t) {
    return is_string($t) && preg_match('/^[a-f0-9]{32}$/', $t) === 1;
}
/** Campañas a las que puede importar el usuario. */
function imp_campanas($auth, $campanaModel) {
    $todas = $campanaModel->obtenerTodas(1) ?: [];
    if ($auth->puedeVerTodasLasCampanas()) { return $todas; }
    $mia = (int)$auth->obtenerCampanaId();
    return array_values(array_filter($todas, function ($c) use ($mia) { return (int)$c['id'] === $mia; }));
}
function imp_volver($url) { header('Location: ' . $url); exit; }

if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
imp_limpiar_viejos($dir);

// ---------------------------------------------------------------- descargas (GET)

if (isset($_GET['plantilla'])) {
    $filas = ImportacionService::filasPlantilla();
    if ($_GET['plantilla'] === 'csv') {
        HojaCalculoService::escribirCsv($filas, 'plantilla_simpatizantes.csv');
    } else {
        HojaCalculoService::enviarXlsx($filas, 'plantilla_simpatizantes.xlsx', 'Simpatizantes');
    }
    exit;
}

$sesion = $_SESSION['importacion'] ?? null;

if (isset($_GET['reporte'])) {
    // Reporte del resultado ya importado
    $res = $_SESSION['importacion_resultado'] ?? null;
    $token = $_GET['reporte'];
    if ($res && imp_token_valido($token) && $res['token'] === $token && is_file(imp_ruta($dir, $token, 'csv'))) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_importacion_' . date('Y-m-d_His') . '.csv"');
        header('X-Content-Type-Options: nosniff');
        readfile(imp_ruta($dir, $token, 'csv'));
        exit;
    }
    $_SESSION['error'] = 'El reporte ya no está disponible.';
    imp_volver($base);
}

// ---------------------------------------------------------------- acciones (POST)

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_proteger();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cancelar') {
        if ($sesion) { @unlink(imp_ruta($dir, $sesion['token'], $sesion['ext'])); }
        unset($_SESSION['importacion']);
        imp_volver($base);
    }

    if ($accion === 'analizar') {
        $campanas = imp_campanas($auth, $campanaModel);
        $campanaId = (int)($_POST['campana_id'] ?? 0);
        $permitida = array_filter($campanas, function ($c) use ($campanaId) { return (int)$c['id'] === $campanaId; });
        $f = $_FILES['archivo'] ?? null;

        if (!$permitida) {
            $_SESSION['error'] = 'Seleccione una campaña válida.';
        } elseif (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $_SESSION['error'] = 'No se recibió el archivo. Revise que no pase de ' . (MAX_FILE_SIZE / 1048576) . ' MB.';
        } elseif ($f['size'] > MAX_FILE_SIZE) {
            $_SESSION['error'] = 'El archivo pasa de ' . (MAX_FILE_SIZE / 1048576) . ' MB. Divídalo en varios.';
        } else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $cabecera = (string)@file_get_contents($f['tmp_name'], false, null, 0, 4);
            if ($ext === 'xls') {
                $_SESSION['error'] = 'El formato .xls (Excel antiguo) no se admite. Guárdelo como .xlsx o CSV.';
            } elseif (!in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
                $_SESSION['error'] = 'Formato no admitido. Suba un archivo .xlsx o .csv.';
            } elseif ($ext === 'xlsx' && $cabecera !== "PK\x03\x04") {
                $_SESSION['error'] = 'El archivo no es un Excel (.xlsx) válido.';
            } else {
                $ext = $ext === 'xlsx' ? 'xlsx' : 'csv';
                $token = bin2hex(random_bytes(16));
                if (move_uploaded_file($f['tmp_name'], imp_ruta($dir, $token, $ext))) {
                    $_SESSION['importacion'] = [
                        'token' => $token, 'ext' => $ext, 'campana_id' => $campanaId,
                        'archivo' => mb_substr(basename($f['name']), 0, 120),
                    ];
                    imp_volver($base . '?paso=revisar');
                }
                $_SESSION['error'] = 'No se pudo guardar el archivo en el servidor (revise permisos de storage/).';
            }
        }
        imp_volver($base);
    }

    if ($accion === 'confirmar') {
        if (!$sesion || !imp_token_valido($_POST['token'] ?? '') || $_POST['token'] !== $sesion['token']) {
            $_SESSION['error'] = 'La importación expiró. Suba el archivo de nuevo.';
            imp_volver($base);
        }
        $ruta = imp_ruta($dir, $sesion['token'], $sesion['ext']);
        try {
            $analisis = ImportacionService::analizar($modelo, HojaCalculoService::leer($ruta, $sesion['ext'], ImportacionService::MAX_FILAS + 2));
        } catch (RuntimeException $e) {
            $analisis = ['error' => $e->getMessage()];
        }
        if (!empty($analisis['error'])) {
            $_SESSION['error'] = $analisis['error'];
            imp_volver($base);
        }

        @set_time_limit(300);
        $res = ImportacionService::importar($modelo, $analisis, (int)$sesion['campana_id'], (int)$auth->obtenerUsuarioId(), $sesion['archivo']);

        // El reporte se guarda ahora: después de importar, el análisis cambiaría (todo sería "duplicado")
        $reporte = ImportacionService::filasReporte($analisis, $res['fallidos']);
        $csv = fopen(imp_ruta($dir, $sesion['token'], 'csv'), 'w');
        fwrite($csv, "\xEF\xBB\xBF");
        foreach ($reporte as $fila) {
            fputcsv($csv, array_map(['HojaCalculoService', 'neutralizar'], $fila), ',', '"', '');
        }
        fclose($csv);
        if ($sesion['ext'] !== 'csv') { @unlink(imp_ruta($dir, $sesion['token'], $sesion['ext'])); }

        (new LogAuditoria())->registrar(
            $auth->obtenerUsuarioId(), 'importar_simpatizantes', 'simpatizantes', null, null,
            ['archivo' => $sesion['archivo'], 'campana_id' => (int)$sesion['campana_id'],
             'filas' => $analisis['resumen']['total'], 'importados' => $res['importados'],
             'omitidos' => $analisis['resumen']['total'] - $res['importados']]
        );

        $_SESSION['importacion_resultado'] = [
            'token' => $sesion['token'], 'archivo' => $sesion['archivo'], 'importados' => $res['importados'],
            'resumen' => $analisis['resumen'], 'fallidos' => count($res['fallidos']), 'hay_reporte' => count($reporte) > 1,
        ];
        unset($_SESSION['importacion']);
        imp_volver($base . '?paso=resultado');
    }

    imp_volver($base);
}

// ---------------------------------------------------------------- vistas (GET)

$paso = $_GET['paso'] ?? '';
$analisis = null;
$resultado = null;

if ($paso === 'revisar') {
    if (!$sesion) { imp_volver($base); }
    $ruta = imp_ruta($dir, $sesion['token'], $sesion['ext']);
    try {
        $analisis = ImportacionService::analizar($modelo, HojaCalculoService::leer($ruta, $sesion['ext'], ImportacionService::MAX_FILAS + 2));
    } catch (RuntimeException $e) {
        $analisis = ['error' => $e->getMessage(), 'filas' => [], 'resumen' => ['total' => 0, 'ok' => 0, 'aviso' => 0, 'duplicado' => 0, 'error' => 0], 'ignoradas' => []];
    }
    if (isset($_GET['descargar'])) {
        HojaCalculoService::escribirCsv(ImportacionService::filasReporte($analisis), 'revision_importacion_' . date('Y-m-d_His') . '.csv');
        exit;
    }
} elseif ($paso === 'resultado') {
    $resultado = $_SESSION['importacion_resultado'] ?? null;
    if (!$resultado) { imp_volver($base); }
}

$campanas = imp_campanas($auth, $campanaModel);
$nombreCampana = '';
if ($sesion && $paso === 'revisar') {
    foreach ($campanas as $c) { if ((int)$c['id'] === (int)$sesion['campana_id']) { $nombreCampana = $c['nombre']; } }
}

$pageTitle = 'Importar simpatizantes';
include __DIR__ . '/../../app/views/layouts/header.php';
$etq = ['ok' => ['Listo', 'on'], 'aviso' => ['Con aviso', 'pend'], 'duplicado' => ['Duplicado', 'bad'], 'error' => ['Error', 'bad']];
?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Simpatizantes</div>
            <h1>Importar simpatizantes</h1>
            <div class="meta"><span><i class="bi bi-file-earmark-spreadsheet"></i>Desde Excel (.xlsx) o CSV</span></div>
        </div>
        <a href="index.php" class="btn btn-soft"><i class="bi bi-arrow-left me-2"></i>Volver al listado</a>
    </div>

<?php if ($paso === 'resultado' && $resultado): ?>
    <!-- Paso 3: resultado -->
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Importación terminada</h5>
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3"><div class="imp-kpi ok"><div class="n"><?php echo number_format($resultado['importados']); ?></div><div class="t">Importados</div></div></div>
                <div class="col-6 col-md-3"><div class="imp-kpi"><div class="n"><?php echo number_format($resultado['resumen']['total']); ?></div><div class="t">Filas del archivo</div></div></div>
                <div class="col-6 col-md-3"><div class="imp-kpi warn"><div class="n"><?php echo number_format($resultado['resumen']['duplicado']); ?></div><div class="t">Duplicados omitidos</div></div></div>
                <div class="col-6 col-md-3"><div class="imp-kpi bad"><div class="n"><?php echo number_format($resultado['resumen']['error'] + $resultado['fallidos']); ?></div><div class="t">Con error</div></div></div>
            </div>
            <p class="text-muted small mb-3">Archivo: <?php echo htmlspecialchars($resultado['archivo']); ?>. Los registros quedaron como <strong>pendientes de validación</strong>.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="index.php?estatus=pendiente" class="btn btn-primary"><i class="bi bi-people me-2"></i>Ver pendientes</a>
                <?php if ($resultado['hay_reporte']): ?>
                    <a href="?reporte=<?php echo htmlspecialchars($resultado['token']); ?>" class="btn btn-soft"><i class="bi bi-download me-2"></i>Descargar reporte de filas omitidas</a>
                <?php endif; ?>
                <a href="importar.php" class="btn btn-soft">Importar otro archivo</a>
            </div>
        </div>
    </div>

<?php elseif ($paso === 'revisar' && $analisis): ?>
    <!-- Paso 2: revisión -->
    <?php if (!empty($analisis['error'])): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($analisis['error']); ?></div>
        <form method="POST" action=""><?php echo csrf_field(); ?><input type="hidden" name="accion" value="cancelar">
            <button class="btn btn-soft">Subir otro archivo</button></form>
    <?php else:
        $r = $analisis['resumen'];
        $importables = $r['ok'] + $r['aviso'];
        $problemas = array_values(array_filter($analisis['filas'], function ($f) { return $f['estado'] !== 'ok'; }));
        $muestra = array_slice(array_values(array_filter($analisis['filas'], function ($f) { return $f['estado'] === 'ok'; })), 0, 5);
    ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                <div>
                    <h5 class="mb-1">Revisión de «<?php echo htmlspecialchars($sesion['archivo']); ?>»</h5>
                    <div class="small text-muted">Campaña: <strong><?php echo htmlspecialchars($nombreCampana); ?></strong> · Aún no se ha guardado nada.</div>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-6 col-md"><div class="imp-kpi"><div class="n"><?php echo number_format($r['total']); ?></div><div class="t">Filas</div></div></div>
                <div class="col-6 col-md"><div class="imp-kpi ok"><div class="n"><?php echo number_format($r['ok']); ?></div><div class="t">Listas</div></div></div>
                <div class="col-6 col-md"><div class="imp-kpi warn"><div class="n"><?php echo number_format($r['aviso']); ?></div><div class="t">Con aviso (se importan)</div></div></div>
                <div class="col-6 col-md"><div class="imp-kpi bad"><div class="n"><?php echo number_format($r['duplicado']); ?></div><div class="t">Duplicadas (se omiten)</div></div></div>
                <div class="col-6 col-md"><div class="imp-kpi bad"><div class="n"><?php echo number_format($r['error']); ?></div><div class="t">Con error (se omiten)</div></div></div>
            </div>
            <?php if (!empty($analisis['ignoradas'])): ?>
                <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Columnas que no se usarán: <?php echo htmlspecialchars(implode(', ', $analisis['ignoradas'])); ?>.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($problemas): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Filas que requieren atención (<?php echo number_format(count($problemas)); ?>)</h5>
            <a href="?paso=revisar&descargar=1" class="btn btn-soft btn-sm"><i class="bi bi-download me-1"></i>Descargar lista</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Fila</th><th>Resultado</th><th>Nombre</th><th>Sección</th><th>Detalle</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($problemas, 0, 200) as $f): ?>
                    <tr>
                        <td class="text-muted"><?php echo (int)$f['n']; ?></td>
                        <td><span class="status-pill <?php echo $etq[$f['estado']][1]; ?>"><?php echo $etq[$f['estado']][0]; ?></span></td>
                        <td><?php echo htmlspecialchars($f['datos']['nombre_completo']); ?></td>
                        <td><span class="sec-pill"><?php echo htmlspecialchars($f['datos']['seccion_electoral'] ?: '—'); ?></span></td>
                        <td class="small"><?php echo htmlspecialchars(implode(' · ', $f['mensajes'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($problemas) > 200): ?>
            <div class="px-3 py-2 small text-muted border-top">Se muestran 200 de <?php echo number_format(count($problemas)); ?>; descargue la lista para verlas todas.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($muestra): ?>
    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Así se guardarán (primeras filas listas)</h5></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Nombre</th><th>Sección</th><th>CURP</th><th>Teléfono</th><th>Correo</th></tr></thead>
                <tbody>
                <?php foreach ($muestra as $f): $d = $f['datos']; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['nombre_completo']); ?></td>
                        <td><span class="sec-pill"><?php echo htmlspecialchars($d['seccion_electoral']); ?></span></td>
                        <td class="mono small"><?php echo htmlspecialchars($d['curp'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($d['telefono'] ?: '—'); ?></td>
                        <td class="small"><?php echo htmlspecialchars($d['email'] ?: '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="action-bar">
        <form method="POST" action="" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="accion" value="cancelar">
            <button type="submit" class="btn btn-soft">Cancelar</button></form>
        <form method="POST" action="" class="d-inline" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Importando…';">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="accion" value="confirmar">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($sesion['token']); ?>">
            <button type="submit" class="btn btn-primary" <?php echo $importables === 0 ? 'disabled' : ''; ?>>
                <i class="bi bi-check-lg me-2"></i>Importar <?php echo number_format($importables); ?> registros
            </button>
        </form>
    </div>
    <?php endif; ?>

<?php else: ?>
    <!-- Paso 1: subir -->
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-upload me-2"></i>1. Suba el archivo</h5></div>
                <div class="card-body">
                    <?php if (empty($campanas)): ?>
                        <div class="alert alert-warning mb-0">No hay una campaña activa a la que pueda importar. Pida a un administrador que le asigne una.</div>
                    <?php else: ?>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="accion" value="analizar">
                        <div class="mb-3">
                            <label class="form-label" for="campana_id">Campaña destino <span class="text-danger">*</span></label>
                            <select class="form-select" name="campana_id" id="campana_id" required>
                                <?php foreach ($campanas as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="dropzone mb-3">
                            <i class="bi bi-file-earmark-spreadsheet fs-1 text-muted"></i>
                            <p class="mb-2">Archivo .xlsx o .csv (máx. <?php echo MAX_FILE_SIZE / 1048576; ?> MB, <?php echo number_format(ImportacionService::MAX_FILAS); ?> filas)</p>
                            <input type="file" class="form-control" name="archivo" accept=".xlsx,.csv,.txt" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-2"></i>Revisar archivo</button>
                        <span class="small text-muted ms-2">No se guarda nada hasta que usted confirme.</span>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Cómo preparar el archivo</h5></div>
                <div class="card-body small">
                    <p>La primera fila debe ser el encabezado. Columnas obligatorias: <strong>Nombre completo</strong>, <strong>Domicilio completo</strong> y <strong>Sección electoral</strong>. También se entienden columnas separadas de nombre y apellidos.</p>
                    <p>Opcionales: clave de elector, CURP, sexo, fecha de nacimiento (dd/mm/aaaa), ciudad, teléfono, WhatsApp, correo, año de registro, vigencia y observaciones.</p>
                    <ul class="mb-3">
                        <li>Si Excel quitó los ceros de la sección (<code>123</code>), el sistema la completa a <code>0123</code>.</li>
                        <li>Los teléfonos se limpian a 10 dígitos (se quitan espacios, guiones y +52).</li>
                        <li>Las filas repetidas (misma CURP, clave, WhatsApp o correo) se omiten; las que solo parecen repetidas se importan con aviso.</li>
                        <li>Todo se importa como <strong>pendiente de validación</strong>.</li>
                    </ul>
                    <div class="d-flex gap-2">
                        <a href="?plantilla=xlsx" class="btn btn-soft btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Plantilla Excel</a>
                        <a href="?plantilla=csv" class="btn btn-soft btn-sm"><i class="bi bi-filetype-csv me-1"></i>Plantilla CSV</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
