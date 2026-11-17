<?php
/**
 * Limpieza de fotos subidas por la ciudadanía: se eliminan los metadatos (EXIF con GPS, fecha, modelo del
 * teléfono, comentarios) sin recomprimir la imagen y sin depender de extensiones opcionales de PHP.
 */

/** JPEG sin los bloques APP1 (EXIF/XMP), APP13 (IPTC) ni comentarios. Devuelve null si el archivo no es un JPEG válido. */
function imagen_jpeg_sin_metadatos($d) {
    $n = strlen($d);
    if ($n < 4 || substr($d, 0, 2) !== "\xFF\xD8") { return null; }
    $salida = "\xFF\xD8";
    $i = 2;
    while ($i < $n) {
        if ($d[$i] !== "\xFF") { return null; }
        while ($i < $n && $d[$i] === "\xFF") { $i++; }
        if ($i >= $n) { return null; }
        $m = ord($d[$i]);
        $i++;
        if ($m === 0xD9) { return $salida . "\xFF\xD9"; }                       // fin de imagen
        if ($m === 0xDA) { return $salida . "\xFF\xDA" . substr($d, $i); }       // datos de la imagen: se copian tal cual
        if (($m >= 0xD0 && $m <= 0xD7) || $m === 0x01) { $salida .= "\xFF" . chr($m); continue; }
        if ($i + 2 > $n) { return null; }
        $largo = (ord($d[$i]) << 8) | ord($d[$i + 1]);
        if ($largo < 2 || $i + $largo > $n) { return null; }
        $segmento = substr($d, $i, $largo);
        $i += $largo;
        if ($m === 0xE1 || $m === 0xED || $m === 0xFE) { continue; }            // EXIF/XMP, IPTC, comentarios
        $salida .= "\xFF" . chr($m) . $segmento;
    }
    return null;
}

/** PNG sin bloques de texto, fecha ni EXIF. Devuelve null si no es un PNG válido. */
function imagen_png_sin_metadatos($d) {
    $n = strlen($d);
    if ($n < 33 || substr($d, 0, 8) !== "\x89PNG\r\n\x1a\n") { return null; }
    $conservar = ['IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'gAMA', 'cHRM', 'sRGB', 'iCCP', 'sBIT'];
    $salida = substr($d, 0, 8);
    $i = 8;
    while ($i + 12 <= $n) {
        $largo = unpack('N', substr($d, $i, 4))[1];
        $tipo = substr($d, $i + 4, 4);
        $fin = $i + 12 + $largo;
        if ($fin > $n) { return null; }
        if (in_array($tipo, $conservar, true)) { $salida .= substr($d, $i, 12 + $largo); }
        $i = $fin;
        if ($tipo === 'IEND') { return $salida; }
    }
    return null;
}

/**
 * Valida y limpia una foto subida.
 * @return array|null ['contenido' => bytes, 'mime' => 'image/jpeg'|'image/png', 'extension' => 'jpg'|'png'], o null si no es una imagen permitida
 */
function imagen_sanear($rutaTemporal) {
    if (!is_file($rutaTemporal)) { return null; }
    $contenido = @file_get_contents($rutaTemporal);
    if ($contenido === false || $contenido === '') { return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($contenido);
    if ($mime === 'image/jpeg') {
        $limpio = imagen_jpeg_sin_metadatos($contenido);
        $ext = 'jpg';
    } elseif ($mime === 'image/png') {
        $limpio = imagen_png_sin_metadatos($contenido);
        $ext = 'png';
    } else {
        return null;
    }
    if ($limpio === null || @getimagesizefromstring($limpio) === false) { return null; }
    return ['contenido' => $limpio, 'mime' => $mime, 'extension' => $ext];
}
