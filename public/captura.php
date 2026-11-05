<?php
/**
 * Captura en campo (móvil): formulario corto, ubicación automática y guardado sin conexión.
 * Las capturas se guardan en el teléfono y se envían solas cuando hay señal.
 * Instalable como aplicación (PWA).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/helpers/security.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/models/Campana.php';
require_once __DIR__ . '/../app/models/Campo.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

$yo = (int)$auth->obtenerUsuarioId();
$campo = new Campo();
$verTodas = $auth->puedeVerTodasLasCampanas();
$campanaPropia = (int)$auth->obtenerCampanaId();

$campanas = [];
if ($verTodas) {
    $campanas = (new Campana())->obtenerTodas(1) ?: [];
}
$sinCampana = !$verTodas && !$campanaPropia;

$metas = $campo->metas(['usuario_id' => $yo, 'vigentes' => 1]);
$zonas = $campo->seccionesDe($yo);

$v = function ($ruta) { return @filemtime(__DIR__ . '/' . $ruta) ?: 1; };
$nombre = $_SESSION['nombre_completo'] ?? ($_SESSION['nombre'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Captura en campo - <?php echo APP_NAME; ?></title>
    <meta name="theme-color" content="#111111">
    <link rel="manifest" href="<?php echo BASE_URL; ?>/public/manifest.php">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/public/img/app-icon-180.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Captura">
    <link href="<?php echo BASE_URL; ?>/public/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/vendor/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/theme.css?v=<?php echo $v('css/theme.css'); ?>">
    <style>
        body { background: #f5f2ee; padding-bottom: env(safe-area-inset-bottom); }
        .cap-bar { position: sticky; top: 0; z-index: 20; background: #111; color: #fff; border-bottom: 3px solid #ff8000;
                   padding: calc(10px + env(safe-area-inset-top)) 16px 10px; display: flex; align-items: center; gap: 10px; }
        .cap-bar h1 { font-size: 1.02rem; margin: 0; font-weight: 700; flex: 1; color: #fff; }
        .cap-bar a { color: #fff; opacity: .85; text-decoration: none; font-size: .85rem; }
        .chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: .75rem; font-weight: 600; }
        .chip.on { background: #e6f0ea; color: #2f6b4a; } .chip.off { background: #fbe9e7; color: #8a2f25; }
        .cap-wrap { max-width: 560px; margin: 0 auto; padding: 14px 14px 90px; }
        .cap-card { background: #fff; border: 1px solid #e9e4de; border-radius: 14px; padding: 14px 16px; margin-bottom: 12px; }
        .cap-wrap .form-control, .cap-wrap .form-select { min-height: 50px; font-size: 1.05rem; border-radius: 10px; }
        .cap-wrap textarea.form-control { min-height: 84px; }
        .cap-wrap label { font-weight: 600; font-size: .9rem; margin-bottom: 4px; }
        .cap-save { position: fixed; left: 0; right: 0; bottom: 0; background: #fff; border-top: 1px solid #e9e4de;
                    padding: 10px 14px calc(10px + env(safe-area-inset-bottom)); z-index: 20; }
        .cap-save .btn { width: 100%; max-width: 532px; display: block; margin: 0 auto; min-height: 54px; font-size: 1.1rem; font-weight: 700; }
        .loc-line { display: flex; align-items: center; gap: 10px; font-size: .9rem; }
        .loc-line i { font-size: 1.3rem; }
        .cola-item { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-top: 1px solid #efeae4; }
        .cola-item:first-child { border-top: 0; }
        .cola-item .txt { flex: 1; min-width: 0; }
        .cola-item .txt b { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cap-toast { position: fixed; left: 14px; right: 14px; bottom: calc(86px + env(safe-area-inset-bottom)); z-index: 30; max-width: 532px; margin: 0 auto;
                     background: #111; color: #fff; padding: 12px 16px; border-radius: 12px; font-weight: 600; box-shadow: 0 8px 24px rgba(0,0,0,.3); }
    </style>
</head>
<body>
    <div class="cap-bar">
        <h1><i class="bi bi-phone me-2"></i>Captura en campo</h1>
        <span class="chip on" id="chipRed"><i class="bi bi-wifi"></i><span>En línea</span></span>
        <a href="<?php echo BASE_URL; ?>/public/dashboard.php" title="Ir al panel"><i class="bi bi-grid-fill"></i></a>
    </div>

    <div class="cap-wrap">
        <?php if ($sinCampana): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Su usuario no tiene una campaña asignada. Pida a su coordinador que se la asigne para poder capturar.</div>
        <?php endif; ?>

        <div id="avisoSesion" class="alert alert-warning" hidden>
            <i class="bi bi-person-lock me-2"></i>Su sesión venció. Las capturas siguen guardadas en este teléfono.
            <a href="<?php echo BASE_URL; ?>/index.php" class="alert-link">Inicie sesión</a> para enviarlas.
        </div>

        <?php foreach ($metas as $m):
            $obj = max(1, (int)$m['objetivo']); $reg = (int)$m['registrados']; $pct = min(100, (int)round($reg * 100 / $obj)); ?>
            <div class="cap-card">
                <div class="d-flex justify-content-between small mb-1">
                    <span><i class="bi bi-bullseye me-1"></i><strong>Su meta</strong><?php echo $m['seccion_electoral'] ? ' · sección ' . htmlspecialchars($m['seccion_electoral']) : ''; ?></span>
                    <span><strong><?php echo number_format($reg); ?></strong> de <?php echo number_format($obj); ?></span>
                </div>
                <div class="progress" style="height:10px"><div class="progress-bar" style="width:<?php echo $pct; ?>%;background:<?php echo $pct >= 100 ? '#2f6b4a' : '#ff8000'; ?>"></div></div>
                <div class="small text-muted mt-1">Hasta el <?php echo date('d/m/Y', strtotime($m['fecha_fin'])); ?></div>
            </div>
        <?php endforeach; ?>

        <?php if ($zonas): ?>
            <div class="cap-card small"><i class="bi bi-diagram-3 me-1"></i><strong>Sus secciones:</strong>
                <?php echo htmlspecialchars(implode(', ', array_slice($zonas, 0, 12))) . (count($zonas) > 12 ? '…' : ''); ?></div>
        <?php endif; ?>

        <!-- Ubicación -->
        <div class="cap-card">
            <div class="loc-line">
                <i class="bi bi-geo-alt text-muted" id="locIcono"></i>
                <div class="flex-fill">
                    <div id="locTexto">Ubicación sin obtener</div>
                    <div class="small text-muted">Se usa solo para ubicar el registro en el mapa. Puede guardar sin ubicación.</div>
                </div>
                <button type="button" class="btn btn-soft btn-sm" id="btnUbicacion">Usar mi ubicación</button>
            </div>
        </div>

        <form id="formSimpatizante" autocomplete="off" novalidate
              data-api="<?php echo BASE_URL; ?>/public/api/verificar-calidad.php"
              data-ver="<?php echo BASE_URL; ?>/public/simpatizantes/ver.php?id="
              data-sync="<?php echo BASE_URL; ?>/public/api/sincronizar-captura.php"
              data-csrf-url="<?php echo BASE_URL; ?>/public/api/csrf.php">
            <?php echo csrf_field(); ?>
            <div id="calidadPanel" class="mb-3" aria-live="polite"></div>
            <div class="cap-card">
                <?php if ($verTodas): ?>
                <div class="mb-3">
                    <label for="campana_id">Campaña</label>
                    <select class="form-select" name="campana_id" id="campana_id">
                        <?php foreach ($campanas as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label for="nombre_completo">Nombre completo <span class="text-danger">*</span></label>
                    <input class="form-control" name="nombre_completo" id="nombre_completo" autocapitalize="words" autocomplete="off" required>
                </div>
                <div class="mb-3">
                    <label for="domicilio_completo">Domicilio <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="domicilio_completo" id="domicilio_completo" rows="2" required></textarea>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-5">
                        <label for="seccion_electoral">Sección <span class="text-danger">*</span></label>
                        <input class="form-control" name="seccion_electoral" id="seccion_electoral" inputmode="numeric" maxlength="4" placeholder="0123" required>
                    </div>
                    <div class="col-7">
                        <label for="whatsapp">WhatsApp</label>
                        <input class="form-control" type="tel" name="whatsapp" id="whatsapp" inputmode="numeric" maxlength="16" placeholder="10 dígitos">
                    </div>
                </div>
                <div class="mb-1">
                    <button class="btn btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#masDatos"><i class="bi bi-plus-circle me-1"></i>Más datos (CURP, clave, correo…)</button>
                </div>
                <div class="collapse" id="masDatos">
                    <div class="mt-3 mb-3"><label for="curp">CURP</label><input class="form-control" name="curp" id="curp" maxlength="18" autocapitalize="characters"></div>
                    <div class="mb-3"><label for="clave_elector">Clave de elector</label><input class="form-control" name="clave_elector" id="clave_elector" maxlength="18" autocapitalize="characters"></div>
                    <div class="row g-3 mb-3">
                        <div class="col-5"><label for="sexo">Sexo</label>
                            <select class="form-select" name="sexo" id="sexo"><option value="">—</option><option value="M">Masculino</option><option value="F">Femenino</option><option value="O">Otro</option></select></div>
                        <div class="col-7"><label for="fecha_nacimiento">Nacimiento</label><input class="form-control" type="date" name="fecha_nacimiento" id="fecha_nacimiento"></div>
                    </div>
                    <div class="mb-3"><label for="telefono">Teléfono</label><input class="form-control" type="tel" name="telefono" id="telefono" inputmode="numeric" maxlength="16"></div>
                    <div class="mb-3"><label for="email">Correo</label><input class="form-control" type="email" name="email" id="email" autocapitalize="none"></div>
                    <div class="mb-3"><label for="ciudad">Ciudad o municipio</label><input class="form-control" name="ciudad" id="ciudad"></div>
                    <div class="mb-1"><label for="observaciones">Observaciones</label><textarea class="form-control" name="observaciones" id="observaciones" rows="2"></textarea></div>
                </div>
            </div>
            <div class="cap-save"><button type="submit" class="btn btn-primary" id="btnGuardar" <?php echo $sinCampana ? 'disabled' : ''; ?>><i class="bi bi-check-lg me-2"></i>Guardar registro</button></div>
        </form>

        <!-- Cola -->
        <div class="cap-card" id="cardCola" hidden>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <strong><i class="bi bi-cloud-arrow-up me-1"></i>Por enviar (<span id="numCola">0</span>)</strong>
                <button type="button" class="btn btn-soft btn-sm" id="btnSync">Enviar ahora</button>
            </div>
            <div id="listaCola"></div>
        </div>
        <p class="small text-muted text-center">Sesión de <?php echo htmlspecialchars($nombre); ?> · Las capturas se guardan en el teléfono hasta que se envían.</p>
    </div>

    <div class="cap-toast" id="toast" hidden></div>

    <script src="<?php echo BASE_URL; ?>/public/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/calidad-datos.js?v=<?php echo $v('js/calidad-datos.js'); ?>"></script>
    <script>window.CAPTURA = { sw: "<?php echo BASE_URL; ?>/public/sw.js", scope: "<?php echo BASE_URL; ?>/public/", login: "<?php echo BASE_URL; ?>/index.php" };</script>
    <script src="<?php echo BASE_URL; ?>/public/js/captura-movil.js?v=<?php echo $v('js/captura-movil.js'); ?>"></script>
</body>
</html>
