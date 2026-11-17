<?php
/**
 * POST /public/api/app/verificar.php
 * Revisa un registro antes de guardarlo: formato de CURP y clave, duplicados y diferencias entre datos.
 * Respuesta: { errores{}, avisos[], duplicados[] } (nombres enmascarados para quien no ve todo el padrón).
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../app/controllers/SimpatizanteController.php';

app_metodo('POST');
[$auth] = app_autenticar();

$in = app_cuerpo();
$datos = [];
foreach (['nombre_completo', 'sexo', 'fecha_nacimiento', 'clave_elector', 'curp', 'seccion_electoral', 'telefono', 'whatsapp', 'email'] as $c) {
    $datos[$c] = isset($in[$c]) && is_string($in[$c]) ? mb_substr($in[$c], 0, 200) : '';
}
$res = (new SimpatizanteController())->revisarCalidad($datos, null);
app_json(200, ['ok' => true] + $res);
