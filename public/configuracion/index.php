<?php
/**
 * Configuración del Sistema
 * Solo para Super Admin
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/LogAuditoria.php';
require_once __DIR__ . '/../../app/services/BackupService.php';
require_once __DIR__ . '/../../app/services/DemoDataService.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin']);

$db = Database::getInstance();
$logModel = new LogAuditoria();
$success = '';
$errores = [];

// Parámetros actuales, indexados por clave
$cargar = function () use ($db) {
    $lista = [];
    foreach (($db->query("SELECT * FROM configuracion ORDER BY clave") ?: []) as $c) { $lista[$c['clave']] = $c; }
    return $lista;
};
$configuraciones = $cargar();

// Notas que se muestran bajo ciertos parámetros
$notas = [
    'backup_automatico' => 'Requiere programar la tarea en el servidor (ver la tarjeta Respaldos).',
    'reporte_semanal_activo' => 'Requiere la tarea en cPanel > Tareas Cron: 0 7 * * 1  php ' . dirname(__DIR__, 2) . '/cron/reporte_semanal.php (lunes 7:00).',
];

// Presentación: nombre amigable y grupo de cada parámetro conocido
$meta = [
    'sistema_nombre'             => ['Nombre del sistema', 'General'],
    'registro_publico_habilitado'=> ['Registro público habilitado', 'Registro público'],
    'terminos_condiciones'       => ['Términos y condiciones', 'Registro público'],
    'max_intentos_login'         => ['Intentos de acceso permitidos', 'Seguridad'],
    'tiempo_bloqueo_minutos'     => ['Minutos de bloqueo tras exceder los intentos', 'Seguridad'],
    'notificaciones_email'       => ['Notificaciones por correo', 'Notificaciones y respaldos'],
    'notificaciones_whatsapp'    => ['Notificaciones por WhatsApp', 'Notificaciones y respaldos'],
    'backup_automatico'          => ['Respaldos automáticos', 'Notificaciones y respaldos'],
    'reporte_semanal_activo'     => ['Reporte semanal por correo (PDF)', 'Notificaciones y respaldos'],
];
$ordenGrupos = ['General', 'Registro público', 'Seguridad', 'Notificaciones y respaldos', 'Otros'];

// Datos de demostración: generar o eliminar
$credencialesDemo = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', ['demo_generar', 'demo_eliminar', 'demo_accesos'], true)) {
    try {
        if ($_POST['accion'] === 'demo_accesos') {
            $credencialesDemo = DemoDataService::crearAccesos();
            $logModel->registrar($auth->obtenerUsuarioId(), 'crear_accesos_demo', 'usuarios', null, null, ['usuarios' => array_column($credencialesDemo['accesos'], 'usuario')]);
            $success = 'Accesos de demostración creados. Copie las contraseñas ahora: no se vuelven a mostrar.';
        } elseif ($_POST['accion'] === 'demo_generar') {
            $cantidad = (int)($_POST['demo_cantidad'] ?? 1200);
            if ($cantidad < 100 || $cantidad > 5000) {
                $errores[] = 'La cantidad debe estar entre 100 y 5000 registros.';
            } else {
                $r = DemoDataService::generar($cantidad);
                $logModel->registrar($auth->obtenerUsuarioId(), 'generar_datos_demo', 'simpatizantes', null, null, ['registros' => $r['total'], 'por_estado' => $r['por_estado']]);
                $success = number_format($r['total']) . ' registros de ejemplo creados: Querétaro ' . $r['por_estado']['Querétaro']
                         . ', Guanajuato ' . $r['por_estado']['Guanajuato'] . ', Estado de México ' . $r['por_estado']['Estado de México'] . '.';
            }
        } else {
            $r = DemoDataService::eliminar();
            $logModel->registrar($auth->obtenerUsuarioId(), 'eliminar_datos_demo', 'simpatizantes', null, null, $r);
            $success = 'Datos de ejemplo eliminados: ' . number_format($r['simpatizantes']) . ' registros'
                     . ($r['capturistas'] ? ', ' . $r['capturistas'] . ' capturistas ficticios' : '')
                     . ($r['campanas'] ? ' y ' . $r['campanas'] . ' campaña de ejemplo' : '') . '.';
        }
    } catch (Throwable $e) {
        error_log('Error en datos de demostración: ' . $e->getMessage());
        $errores[] = 'No se pudo completar la operación con los datos de ejemplo: ' . $e->getMessage();
    }

// Crear un respaldo manual
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'respaldo') {
    try {
        $r = BackupService::crear();
        $logModel->registrar($auth->obtenerUsuarioId(), 'crear_respaldo', 'configuracion', null, null, [
            'base_datos' => $r['base_datos']['archivo'],
            'archivos' => $r['archivos']['archivo'] ?? null,
        ]);
        $success = 'Respaldo creado: ' . $r['base_datos']['archivo'] . ' (' . BackupService::formatoTamano($r['base_datos']['tam']) . ')'
                 . ($r['archivos'] ? ' y ' . $r['archivos']['archivo'] : '') . '.';
        foreach ($r['avisos'] as $aviso) { $success .= ' ' . $aviso; }
    } catch (Throwable $e) {
        error_log('Error al crear respaldo manual: ' . $e->getMessage());
        $errores[] = 'No se pudo crear el respaldo. Revise los permisos de escritura de la carpeta storage/backups.';
    }

// Guardar cambios (solo claves existentes, validadas por tipo)
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $antes = [];
    $despues = [];

    foreach ($configuraciones as $clave => $cfg) {
        $campo = 'config_' . $clave;
        if (!array_key_exists($campo, $_POST) || $cfg['tipo'] === 'color' || $clave === 'notificaciones_whatsapp') { continue; }
        $valor = is_string($_POST[$campo]) ? trim($_POST[$campo]) : '';
        $nombre = $meta[$clave][0] ?? $clave;

        if ($cfg['tipo'] === 'boolean') {
            if (!in_array($valor, ['true', 'false'], true)) { $errores[] = "$nombre: valor no válido"; continue; }
        } elseif ($cfg['tipo'] === 'numero') {
            if (!ctype_digit($valor) || (int)$valor > 100000) { $errores[] = "$nombre: debe ser un número entero entre 0 y 100000"; continue; }
        } else {
            $limite = ($clave === 'terminos_condiciones') ? 20000 : 255;
            if (mb_strlen($valor) > $limite) { $errores[] = "$nombre: no puede exceder $limite caracteres"; continue; }
            if ($clave === 'sistema_nombre' && $valor === '') { $errores[] = "$nombre: es obligatorio"; continue; }
        }

        if ($valor !== (string)$cfg['valor']) {
            $antes[$clave] = $cfg['valor'];
            $despues[$clave] = $valor;
        }
    }

    if (empty($errores)) {
        foreach ($despues as $clave => $valor) {
            $db->execute("UPDATE configuracion SET valor = ? WHERE clave = ?", [$valor, $clave]);
        }
        if (!empty($despues)) {
            $logModel->registrar($auth->obtenerUsuarioId(), 'actualizar_configuracion', 'configuracion', null, $antes, $despues);
            $success = 'Configuración actualizada correctamente (' . count($despues) . ' ' . (count($despues) === 1 ? 'cambio' : 'cambios') . ').';
        } else {
            $success = 'No hubo cambios que guardar.';
        }
        $configuraciones = $cargar();
    }
}

// Estado de la cola de correos (si la tabla aún no existe, queda en cero)
$cola = ['pending' => 0, 'sent' => 0, 'failed' => 0];
$colaMasAntiguo = null;
foreach (($db->query("SELECT status, COUNT(*) AS c, MIN(created_at) AS m FROM email_queue GROUP BY status") ?: []) as $fila) {
    if (isset($cola[$fila['status']])) { $cola[$fila['status']] = (int)$fila['c']; }
    if ($fila['status'] === 'pending') { $colaMasAntiguo = $fila['m']; }
}
$colaMinutos = $colaMasAntiguo ? (int)((time() - strtotime($colaMasAntiguo)) / 60) : 0;
$colaAtascada = ($cola['pending'] > 0 && $colaMinutos >= 15);
$rutaProyecto = str_replace('\\', '/', BASE_PATH);
$respaldos = BackupService::listar(6);
$totalDemo = DemoDataService::contar();
$respaldoAuto = isset($configuraciones['backup_automatico']) && $configuraciones['backup_automatico']['valor'] === 'true';

// Agrupar para mostrar
$grupos = array_fill_keys($ordenGrupos, []);
$hayColores = false;
foreach ($configuraciones as $clave => $cfg) {
    if ($cfg['tipo'] === 'color') { $hayColores = true; continue; }
    if (strpos($clave, 'ocr_') === 0 || $clave === 'reporte_semanal_ultimo') { continue; } // función retirada
    $grupos[$meta[$clave][1] ?? 'Otros'][] = $cfg;
}

$pageTitle = 'Configuración';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Administración</div>
            <h1>Configuración del sistema</h1>
            <div class="meta"><span><i class="bi bi-sliders"></i>Parámetros generales. Cada cambio queda registrado en la auditoría.</span></div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <strong>No se guardó nada. Corrija lo siguiente:</strong>
            <ul class="mb-0 mt-2"><?php foreach ($errores as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($credencialesDemo): ?>
        <div class="card mb-3 cred-card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-key me-2"></i>Accesos de demostración</h5></div>
            <div class="card-body">
                <p class="small mb-3">Entre en <strong><?php echo htmlspecialchars(BASE_URL); ?>/</strong> con estas cuentas para ver el sistema como cada nivel.
                    <strong>Copie las contraseñas ahora</strong>: solo se muestran en esta página y en la base queda únicamente su versión cifrada.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <thead><tr><th>Nivel</th><th>Usuario</th><th>Contraseña</th><th>Qué puede ver</th></tr></thead>
                        <tbody>
                        <?php foreach ($credencialesDemo['accesos'] as $a): ?>
                            <tr>
                                <td><span class="rol-pill rol-<?php echo htmlspecialchars($a['rol']); ?>"><?php echo htmlspecialchars(ucfirst($a['rol'])); ?></span></td>
                                <td class="mono"><?php echo htmlspecialchars($a['usuario']); ?></td>
                                <td class="mono fw-bold"><?php echo htmlspecialchars($a['clave']); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($a['ve']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="small text-muted">
                    El super administrador no se crea aquí: use su propia cuenta.
                    <?php if ($credencialesDemo['registros_capturista'] > 0): ?>El capturista tiene <?php echo (int)$credencialesDemo['registros_capturista']; ?> registros de ejemplo asignados.
                    <?php else: ?>Genere primero los datos de ejemplo y pulse de nuevo "Crear accesos" para que el capturista tenga registros que ver.<?php endif; ?>
                    Si pulsa el botón otra vez se generan contraseñas nuevas. Estas cuentas se borran con "Eliminar datos de ejemplo".
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <form method="POST" action="">
<?php echo csrf_field(); ?>
                <?php foreach ($grupos as $nombreGrupo => $items): if (empty($items)) continue; ?>
                    <div class="card mb-3">
                        <div class="card-header"><h5 class="mb-0"><?php echo htmlspecialchars($nombreGrupo); ?></h5></div>
                        <div class="card-body">
                            <?php foreach ($items as $cfg):
                                $clave = $cfg['clave'];
                                $campo = 'config_' . $clave;
                                $etiqueta = $meta[$clave][0] ?? $clave;
                                $bloqueado = ($clave === 'notificaciones_whatsapp');
                            ?>
                                <div class="cfg-row">
                                    <?php if ($cfg['tipo'] === 'boolean'): ?>
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <label class="form-label mb-0" for="<?php echo htmlspecialchars($campo); ?>"><?php echo htmlspecialchars($etiqueta); ?>
                                                    <?php if ($bloqueado): ?><span class="status-pill off ms-1">No disponible</span><?php endif; ?></label>
                                                <?php if ($bloqueado): ?>
                                                    <div class="form-text mt-0">Aún no hay una integración de envío por WhatsApp configurada, por lo que este aviso no se puede activar.</div>
                                                <?php else: ?>
                                                    <?php if (!empty($cfg['descripcion'])): ?><div class="form-text mt-0"><?php echo htmlspecialchars($cfg['descripcion']); ?></div><?php endif; ?>
                                                    <?php if (!empty($notas[$clave])): ?><div class="form-text mt-0"><?php echo htmlspecialchars($notas[$clave]); ?></div><?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="form-check form-switch m-0">
                                                <?php if (!$bloqueado): ?><input type="hidden" name="<?php echo htmlspecialchars($campo); ?>" value="false"><?php endif; ?>
                                                <input class="form-check-input" type="checkbox" role="switch" id="<?php echo htmlspecialchars($campo); ?>"
                                                       <?php if (!$bloqueado): ?>name="<?php echo htmlspecialchars($campo); ?>" value="true"<?php endif; ?>
                                                       <?php echo ($cfg['valor'] === 'true' && !$bloqueado) ? 'checked' : ''; ?> <?php echo $bloqueado ? 'disabled' : ''; ?>>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <label class="form-label" for="<?php echo htmlspecialchars($campo); ?>"><?php echo htmlspecialchars($etiqueta); ?></label>
                                        <?php if ($cfg['tipo'] === 'numero'): ?>
                                            <input type="number" min="0" max="100000" step="1" class="form-control" id="<?php echo htmlspecialchars($campo); ?>"
                                                   name="<?php echo htmlspecialchars($campo); ?>" value="<?php echo htmlspecialchars($cfg['valor']); ?>">
                                        <?php elseif ($clave === 'terminos_condiciones'): ?>
                                            <textarea class="form-control" rows="9" id="<?php echo htmlspecialchars($campo); ?>"
                                                      name="<?php echo htmlspecialchars($campo); ?>"><?php echo htmlspecialchars($cfg['valor']); ?></textarea>
                                        <?php else: ?>
                                            <input type="text" class="form-control" id="<?php echo htmlspecialchars($campo); ?>" maxlength="255"
                                                   name="<?php echo htmlspecialchars($campo); ?>" value="<?php echo htmlspecialchars($cfg['valor']); ?>">
                                        <?php endif; ?>
                                        <?php if (!empty($cfg['descripcion'])): ?><div class="form-text"><?php echo htmlspecialchars($cfg['descripcion']); ?></div><?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="action-bar">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Guardar configuración</button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Sistema</h5></div>
                <div class="card-body">
                    <dl class="dl-grid" style="grid-template-columns: 1fr 1fr;">
                        <div><dt>Versión</dt><dd><?php echo htmlspecialchars(APP_VERSION); ?></dd></div>
                        <div><dt>PHP</dt><dd><?php echo htmlspecialchars(PHP_VERSION); ?></dd></div>
                    </dl>
                </div>
            </div>
            <!-- Respaldos -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-hdd me-2"></i>Respaldos</h5>
                    <span class="status-pill <?php echo $respaldoAuto ? 'on' : 'off'; ?>"><?php echo $respaldoAuto ? 'Automático' : 'Manual'; ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($respaldos)): ?>
                        <p class="small text-muted mb-3">Aún no se ha creado ningún respaldo.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-3 small">
                            <?php foreach ($respaldos as $b): ?>
                                <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
                                    <span class="text-truncate" title="<?php echo htmlspecialchars($b['archivo']); ?>"><?php echo htmlspecialchars($b['tipo']); ?><br><span class="text-muted"><?php echo date('d/m/Y H:i', $b['fecha']); ?></span></span>
                                    <span class="text-muted text-nowrap"><?php echo BackupService::formatoTamano($b['tam']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <form method="POST" action="" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerHTML = '<span class=&quot;spinner-border spinner-border-sm me-2&quot;></span>Creando...';">
<?php echo csrf_field(); ?>
                        <input type="hidden" name="accion" value="respaldo">
                        <button type="submit" class="btn btn-soft w-100"><i class="bi bi-cloud-arrow-down me-2"></i>Crear respaldo ahora</button>
                    </form>
                    <div class="small text-muted mt-3">
                        Se guardan en <code>storage/backups/</code> (sin acceso desde la web) y se conservan los últimos <?php echo BackupService::CONSERVAR; ?>.
                        Para que sean automáticos, programe en cPanel &gt; Tareas Cron, una vez al día:
                        <div class="mono mt-2 p-2 rounded cron-line">30 2 * * *  php <?php echo htmlspecialchars($rutaProyecto); ?>/cron/backup.php</div>
                        <div class="mt-2">Descargue periódicamente los respaldos a un lugar fuera del servidor.</div>
                    </div>
                </div>
            </div>

            <!-- Datos de demostración -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-database-add me-2"></i>Datos de ejemplo</h5>
                    <span class="status-pill <?php echo $totalDemo > 0 ? 'on' : 'off'; ?>"><?php echo $totalDemo > 0 ? number_format($totalDemo) . ' activos' : 'Ninguno'; ?></span>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Crea simpatizantes <strong>simulados</strong> (nombres y teléfonos inventados) de Querétaro, Guanajuato y Estado de México, con fechas de los últimos 60 días, para probar o presentar el sistema.</p>
                    <form method="POST" action="" class="mb-2" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerHTML = '<span class=&quot;spinner-border spinner-border-sm me-2&quot;></span>Generando...';">
<?php echo csrf_field(); ?>
                        <input type="hidden" name="accion" value="demo_generar">
                        <label class="form-label small" for="demo_cantidad">Cantidad de registros</label>
                        <input type="number" class="form-control mb-2" id="demo_cantidad" name="demo_cantidad" value="1200" min="100" max="5000" step="100">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-magic me-2"></i>Generar datos de ejemplo</button>
                    </form>
                    <form method="POST" action="" class="mb-2">
<?php echo csrf_field(); ?>
                        <input type="hidden" name="accion" value="demo_accesos">
                        <button type="submit" class="btn btn-soft w-100"><i class="bi bi-people me-2"></i>Crear accesos por nivel (admin, coordinador, candidato, capturista)</button>
                    </form>
                    <?php if ($totalDemo > 0): ?>
                        <form method="POST" action="" onsubmit="return confirm('Se borrarán los <?php echo $totalDemo; ?> registros de ejemplo. Los datos reales no se tocan. ¿Continuar?');">
<?php echo csrf_field(); ?>
                            <input type="hidden" name="accion" value="demo_eliminar">
                            <button type="submit" class="btn btn-soft w-100"><i class="bi bi-trash3 me-2"></i>Eliminar datos de ejemplo</button>
                        </form>
                    <?php endif; ?>
                    <div class="small text-muted mt-3">
                        Los registros de ejemplo se identifican con <code>[DEMO]</code> en Observaciones y se mezclan con los reales
                        (listados, mapa, reportes y exportaciones) hasta que los elimine. <strong>No los use en una base con datos reales en operación.</strong>
                    </div>
                </div>
            </div>

            <!-- Cola de correos -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-envelope-paper me-2"></i>Cola de correos</h5>
                    <span class="status-pill <?php echo $colaAtascada ? 'off' : 'on'; ?>"><?php echo $colaAtascada ? 'Sin procesar' : 'En orden'; ?></span>
                </div>
                <div class="card-body">
                    <dl class="dl-grid" style="grid-template-columns: repeat(3, 1fr);">
                        <div><dt>Pendientes</dt><dd><?php echo $cola['pending']; ?></dd></div>
                        <div><dt>Enviados</dt><dd><?php echo $cola['sent']; ?></dd></div>
                        <div><dt>Fallidos</dt><dd><?php echo $cola['failed']; ?></dd></div>
                    </dl>
                    <?php if ($colaAtascada): ?>
                        <div class="alert alert-warning small mt-3 mb-0">
                            Hay <?php echo $cola['pending']; ?> correo(s) esperando desde hace <?php echo $colaMinutos; ?> minutos. Esto indica que la tarea programada no se está ejecutando.
                        </div>
                    <?php endif; ?>
                    <div class="small text-muted mt-3">
                        Para que los correos (como la recuperación de contraseña) salgan siempre, programe en cPanel &gt; Tareas Cron, cada 5 minutos:
                        <div class="mono mt-2 p-2 rounded cron-line">*/5 * * * *  php <?php echo htmlspecialchars($rutaProyecto); ?>/process_email_queue.php</div>
                    </div>
                </div>
            </div>

            <?php if ($hayColores): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="mb-2"><i class="bi bi-palette me-2 text-muted"></i>Colores</h6>
                    <p class="small text-muted mb-0">La imagen del sistema usa la paleta institucional (azul marino y gris). Los colores guardados anteriormente se conservan pero ya no se aplican.</p>
                </div>
            </div>
            <?php endif; ?>
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2"><i class="bi bi-exclamation-triangle me-2 text-muted"></i>Precaución</h6>
                    <p class="small text-muted mb-0">Estos valores afectan a todo el sistema. Por ejemplo, desactivar el registro público oculta el formulario de inscripción abierto a cualquier persona.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.cred-card { border-left: 5px solid var(--brand) !important; }
.cron-line { background: var(--slate-100); border: 1px solid var(--slate-200); word-break: break-all; font-size: .74rem; }
.cfg-row { padding: 14px 0; border-bottom: 1px solid var(--slate-100); }
.cfg-row:first-child { padding-top: 0; }
.cfg-row:last-child { padding-bottom: 0; border-bottom: 0; }
</style>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
