<?php
/**
 * POST /public/api/app/sincronizar.php
 * Cuerpo JSON: una captura con su `uuid` (ver CapturaService::CAMPOS) + `temas` (arreglo de claves) + `campana_id` (solo admin).
 * Idempotente: reenviar el mismo uuid no crea otro registro.
 *
 *   200 { ok:true, id, repetido?, avisos[] }                  → guardada
 *   422 { ok:false, permanente:true, error, errores{} }       → datos inválidos o duplicado: no reintentar
 *   401 { sesion_vencida:true } / 426 { actualizar:true } / 503 → reintentar o pedir inicio de sesión
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../app/services/CapturaService.php';

app_metodo('POST');
[$auth] = app_autenticar();

$in = app_cuerpo();
if (!$in) {
    app_json(422, ['ok' => false, 'permanente' => true, 'error' => 'Datos no válidos.']);
}
[$codigo, $cuerpo] = CapturaService::procesar($auth, $in, 'app');
app_json($codigo, $cuerpo);
