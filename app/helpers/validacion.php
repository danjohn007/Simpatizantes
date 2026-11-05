<?php
/**
 * Normalización y validación de datos de simpatizantes (funciones puras, sin base de datos).
 *
 * Se usa al capturar, al editar, en el registro público y en la importación masiva,
 * para que todos apliquen exactamente las mismas reglas.
 */

/** Quita acentos y pasa a mayúsculas (para comparar nombres). */
function vd_sin_acentos($texto) {
    // Tabla propia para el español: iconv//TRANSLIT cambia según el sistema (en algunos devuelve "?")
    $texto = strtr((string)$texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
    ]);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($t !== false && $t !== '') {
            $texto = $t;
        }
    }
    return strtoupper(preg_replace('/[^A-Za-z0-9 ]/', '', $texto));
}

/** Recorta y colapsa espacios repetidos. */
function vd_normalizar_texto($texto) {
    $texto = preg_replace('/\s+/u', ' ', trim((string)$texto));
    return $texto === null ? '' : $texto;
}

/** CURP y clave de elector: mayúsculas, sin espacios ni guiones. */
function vd_normalizar_codigo($codigo) {
    return strtoupper(preg_replace('/[\s\-_.]+/', '', trim((string)$codigo)));
}

/** Teléfono a 10 dígitos (quita +52, 044, 045, espacios, guiones y paréntesis). */
function vd_normalizar_telefono($telefono) {
    $d = preg_replace('/\D+/', '', (string)$telefono);
    if ($d === '') {
        return '';
    }
    if (strlen($d) > 10 && strpos($d, '521') === 0 && strlen($d) === 13) { $d = substr($d, 3); }
    elseif (strlen($d) > 10 && strpos($d, '52') === 0 && strlen($d) === 12) { $d = substr($d, 2); }
    elseif (strlen($d) === 13 && (strpos($d, '044') === 0 || strpos($d, '045') === 0)) { $d = substr($d, 3); }
    return $d;
}

/** Sección electoral a 4 dígitos (Excel suele quitar los ceros a la izquierda). */
function vd_normalizar_seccion($seccion) {
    $s = preg_replace('/\D+/', '', (string)$seccion);
    if ($s === '' || strlen($s) > 4) {
        return $s;
    }
    return str_pad($s, 4, '0', STR_PAD_LEFT);
}

/**
 * Fecha en varios formatos → Y-m-d, o null si no se reconoce.
 * Acepta 2025-03-14, 14/03/2025, 14-03-2025 y el número de serie de Excel.
 */
function vd_normalizar_fecha($valor) {
    $v = trim((string)$valor);
    if ($v === '') {
        return null;
    }
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m)) {
        $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
    } elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $v, $m)) {
        $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
    } elseif (preg_match('/^\d{4,6}(\.\d+)?$/', $v)) {
        // Número de serie de Excel (días desde 1899-12-30)
        $dt = new DateTime('1899-12-30');
        $dt->modify('+' . (int)floor((float)$v) . ' days');
        return $dt->format('Y-m-d');
    } else {
        return null;
    }
    if (!checkdate($mo, $d, $y) || $y < 1900 || $y > (int)date('Y')) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

/** Sexo en cualquiera de sus variantes → 'M' (masculino), 'F' (femenino), 'O' (otro) o ''. */
function vd_normalizar_sexo($valor) {
    $v = vd_sin_acentos($valor);
    if ($v === '') { return ''; }
    if (in_array($v, ['M', 'H', 'HOMBRE', 'MASCULINO', 'MASC'], true)) { return 'M'; }
    if (in_array($v, ['F', 'MUJER', 'FEMENINO', 'FEM'], true)) { return 'F'; }
    return 'O';
}

/**
 * Dígito verificador de la CURP (algoritmo oficial de RENAPO) sobre los primeros 17 caracteres.
 */
function vd_curp_digito_verificador($curp17) {
    if (strlen($curp17) < 17) {
        return null;
    }
    // Diccionario oficial: la Ñ ocupa la posición 24, por eso O..Z van un lugar adelante
    $dic = '0123456789ABCDEFGHIJKLMN*OPQRSTUVWXYZ';
    $suma = 0;
    for ($i = 0; $i < 17; $i++) {
        $pos = strpos($dic, $curp17[$i]);
        if ($pos === false) { return null; }
        $suma += $pos * (18 - $i);
    }
    $digito = 10 - ($suma % 10);
    return (string)($digito === 10 ? 0 : $digito);
}

/**
 * Valida una CURP ya normalizada.
 * @return array ['errores' => [...], 'avisos' => [...]]
 *   errores: formato o fecha imposibles (no se debe guardar)
 *   avisos : inconsistencias que conviene revisar (dígito verificador, sexo o fecha distintos)
 */
function vd_validar_curp($curp, $fechaNacimiento = null, $sexo = null) {
    $res = ['errores' => [], 'avisos' => []];
    if ($curp === '') {
        return $res;
    }
    if (!preg_match('/^[A-Z]{4}\d{6}[HMX][A-Z]{2}[A-Z]{3}[0-9A-Z]\d$/', $curp)) {
        $res['errores'][] = 'La CURP debe tener 18 caracteres con el formato oficial (4 letras, 6 dígitos de fecha, sexo, estado, 3 consonantes, homoclave y dígito)';
        return $res;
    }
    $yy = (int)substr($curp, 4, 2);
    $mm = (int)substr($curp, 6, 2);
    $dd = (int)substr($curp, 8, 2);
    // El siglo lo indica el carácter 17: dígito = 1900s, letra = 2000s
    $anio = (ctype_digit($curp[16]) ? 1900 : 2000) + $yy;
    if (!checkdate($mm, $dd, $anio)) {
        $res['errores'][] = 'La fecha dentro de la CURP no es válida';
        return $res;
    }
    $fechaCurp = sprintf('%04d-%02d-%02d', $anio, $mm, $dd);

    if (vd_curp_digito_verificador(substr($curp, 0, 17)) !== $curp[17]) {
        $res['avisos'][] = 'El dígito verificador de la CURP no coincide; revise que esté bien capturada';
    }
    if ($fechaNacimiento && $fechaNacimiento !== $fechaCurp) {
        $res['avisos'][] = 'La fecha de nacimiento no coincide con la de la CURP (' . date('d/m/Y', strtotime($fechaCurp)) . ')';
    }
    if ($sexo === 'M' || $sexo === 'F') {
        $esperado = $sexo === 'M' ? 'H' : 'M';
        if ($curp[10] !== $esperado && $curp[10] !== 'X') {
            $res['avisos'][] = 'El sexo no coincide con el de la CURP';
        }
    }
    return $res;
}

/**
 * Valida una clave de elector ya normalizada (6 letras, 8 dígitos, H/M, 3 dígitos).
 */
function vd_validar_clave_elector($clave, $fechaNacimiento = null, $sexo = null) {
    $res = ['errores' => [], 'avisos' => []];
    if ($clave === '') {
        return $res;
    }
    if (!preg_match('/^[A-Z]{6}\d{8}[HM]\d{3}$/', $clave)) {
        $res['errores'][] = 'La clave de elector debe tener 18 caracteres: 6 letras, 8 dígitos, H o M y 3 dígitos';
        return $res;
    }
    $yy = (int)substr($clave, 6, 2);
    $mm = (int)substr($clave, 8, 2);
    $dd = (int)substr($clave, 10, 2);
    $estado = (int)substr($clave, 12, 2);
    if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) {
        $res['errores'][] = 'La fecha dentro de la clave de elector no es válida';
        return $res;
    }
    if ($estado < 1 || $estado > 33) {
        $res['errores'][] = 'La clave de elector tiene un código de entidad inválido';
        return $res;
    }
    if ($fechaNacimiento) {
        // La clave solo guarda dos dígitos de año: se compara mes, día y año corto
        if (date('ymd', strtotime($fechaNacimiento)) !== sprintf('%02d%02d%02d', $yy, $mm, $dd)) {
            $res['avisos'][] = 'La fecha de nacimiento no coincide con la de la clave de elector';
        }
    }
    if ($sexo === 'M' || $sexo === 'F') {
        if ($clave[14] !== ($sexo === 'M' ? 'H' : 'M')) {
            $res['avisos'][] = 'El sexo no coincide con el de la clave de elector';
        }
    }
    return $res;
}

/** Teléfono (ya normalizado): vacío o exactamente 10 dígitos. */
function vd_telefono_valido($telefono) {
    return $telefono === '' || preg_match('/^\d{10}$/', $telefono) === 1;
}

/** Enmascara un nombre para quien no debe verlo completo: "María González Pérez" → "M*** G*** P***". */
function vd_enmascarar_nombre($nombre) {
    $partes = preg_split('/\s+/u', trim((string)$nombre));
    $salida = [];
    foreach ($partes as $p) {
        if ($p === '') { continue; }
        $salida[] = mb_substr($p, 0, 1) . '***';
    }
    return implode(' ', $salida);
}

/**
 * Normaliza de una vez los campos de un simpatizante (los que no vengan, se dejan como están).
 * Lo usan el controlador (captura/edición/registro público) y la importación masiva.
 */
function vd_normalizar_simpatizante(array $datos) {
    foreach (['nombre_completo', 'domicilio_completo', 'ciudad', 'observaciones'] as $c) {
        if (isset($datos[$c])) { $datos[$c] = vd_normalizar_texto($datos[$c]); }
    }
    foreach (['curp', 'clave_elector'] as $c) {
        if (isset($datos[$c])) { $datos[$c] = vd_normalizar_codigo($datos[$c]); }
    }
    foreach (['telefono', 'whatsapp'] as $c) {
        if (isset($datos[$c])) { $datos[$c] = vd_normalizar_telefono($datos[$c]); }
    }
    if (isset($datos['seccion_electoral'])) { $datos['seccion_electoral'] = vd_normalizar_seccion($datos['seccion_electoral']); }
    if (isset($datos['email'])) { $datos['email'] = strtolower(trim($datos['email'])); }
    return $datos;
}
