<?php
/**
 * Lectura y escritura de hojas de cálculo (CSV y XLSX) sin librerías externas.
 *
 * - leer():        devuelve las filas de un CSV o XLSX como arreglos de texto.
 * - escribirXlsx():genera un .xlsx con todas las celdas como texto (conserva ceros a la izquierda).
 * - escribirCsv(): CSV UTF-8 con BOM, neutralizando fórmulas (CSV injection).
 *
 * Requiere la extensión zip (ya usada por los respaldos).
 */

class HojaCalculoService {
    const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    const MAX_BYTES_XML = 40 * 1024 * 1024; // límite al descomprimir (protege de zip bombs)

    // ------------------------------------------------------------------ lectura

    /**
     * @return array Filas (cada una, un arreglo de strings). Lanza RuntimeException si el archivo no es válido.
     */
    public static function leer($ruta, $extension, $maxFilas = 5001) {
        $extension = strtolower($extension);
        if ($extension === 'xlsx') {
            return self::leerXlsx($ruta, $maxFilas);
        }
        return self::leerCsv($ruta, $maxFilas);
    }

    public static function leerCsv($ruta, $maxFilas = 5001) {
        $contenido = @file_get_contents($ruta);
        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el archivo.');
        }
        if (strpos(substr($contenido, 0, 4096), "\0") !== false) {
            throw new RuntimeException('El archivo no parece ser un CSV de texto.');
        }
        // BOM y codificación (Excel en español suele guardar como Windows-1252)
        if (substr($contenido, 0, 3) === "\xEF\xBB\xBF") {
            $contenido = substr($contenido, 3);
        }
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        // Delimitador: el más frecuente en la primera línea con datos
        $primera = strtok($contenido, "\r\n");
        $mejor = ','; $max = -1;
        foreach ([',', ';', "\t", '|'] as $d) {
            $n = substr_count((string)$primera, $d);
            if ($n > $max) { $max = $n; $mejor = $d; }
        }

        $flujo = fopen('php://temp', 'r+');
        fwrite($flujo, $contenido);
        rewind($flujo);
        $filas = [];
        while (($fila = fgetcsv($flujo, 0, $mejor, '"', '')) !== false) {
            if ($fila === [null]) { continue; } // línea vacía
            $filas[] = array_map(function ($v) { return trim((string)$v); }, $fila);
            if (count($filas) >= $maxFilas) { break; }
        }
        fclose($flujo);
        return $filas;
    }

    public static function leerXlsx($ruta, $maxFilas = 5001) {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('El servidor no tiene habilitada la extensión zip; guarde el archivo como CSV.');
        }
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new RuntimeException('El archivo no es un Excel (.xlsx) válido.');
        }
        try {
            // Hoja: la primera según el libro; si no se encuentra, la primera del paquete
            $rutaHoja = self::rutaPrimeraHoja($zip);
            $xmlHoja = self::leerEntrada($zip, $rutaHoja);
            if ($xmlHoja === null) {
                throw new RuntimeException('No se encontró ninguna hoja en el archivo de Excel.');
            }
            $compartidas = [];
            $xmlCompartidas = self::leerEntrada($zip, 'xl/sharedStrings.xml');
            if ($xmlCompartidas !== null) {
                $compartidas = self::cadenasCompartidas($xmlCompartidas);
            }
        } finally {
            $zip->close();
        }

        $ns = self::NS;
        $hoja = self::dom($xmlHoja);
        $filas = [];
        foreach ($hoja->getElementsByTagNameNS($ns, 'row') as $row) {
            $celdas = [];
            $maxCol = -1;
            $pos = 0;
            foreach ($row->childNodes as $c) {
                if (!($c instanceof DOMElement) || $c->localName !== 'c') { continue; }
                $ref = $c->getAttribute('r');
                $col = $ref !== '' ? self::indiceColumna($ref) : $pos;
                $pos = $col + 1;
                $tipo = $c->getAttribute('t');
                $v = null; $is = null;
                foreach ($c->childNodes as $h) {
                    if (!($h instanceof DOMElement)) { continue; }
                    if ($h->localName === 'v') { $v = $h->textContent; }
                    elseif ($h->localName === 'is') { $is = $h; }
                }
                if ($tipo === 's') {
                    $valor = $compartidas[(int)$v] ?? '';
                } elseif ($tipo === 'inlineStr') {
                    $valor = $is ? self::textoRico($is) : '';
                } elseif ($tipo === 'b') {
                    $valor = $v === '1' ? 'Sí' : 'No';
                } else {
                    $valor = (string)$v;
                    // Enteros guardados como 123.0
                    if ($valor !== '' && is_numeric($valor) && strpos($valor, '.') !== false && (float)$valor == floor((float)$valor) && abs((float)$valor) < 1e15) {
                        $valor = (string)(int)$valor;
                    }
                }
                $celdas[$col] = trim($valor);
                if ($col > $maxCol) { $maxCol = $col; }
            }
            $fila = [];
            for ($i = 0; $i <= $maxCol; $i++) { $fila[] = $celdas[$i] ?? ''; }
            if ($maxCol >= 0 && implode('', $fila) !== '') {
                $filas[] = $fila;
            }
            if (count($filas) >= $maxFilas) { break; }
        }
        return $filas;
    }

    private static function rutaPrimeraHoja(ZipArchive $zip) {
        $libro = self::leerEntrada($zip, 'xl/workbook.xml');
        $rels = self::leerEntrada($zip, 'xl/_rels/workbook.xml.rels');
        if ($libro !== null && $rels !== null) {
            $wb = self::dom($libro);
            $hojas = $wb->getElementsByTagNameNS(self::NS, 'sheet');
            if ($hojas->length > 0) {
                $rid = $hojas->item(0)->getAttributeNS(self::NS_REL, 'id');
                foreach (self::dom($rels)->getElementsByTagName('Relationship') as $rel) {
                    if ($rel->getAttribute('Id') === $rid) {
                        $destino = ltrim($rel->getAttribute('Target'), '/');
                        return strpos($destino, 'xl/') === 0 ? $destino : 'xl/' . $destino;
                    }
                }
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    private static function cadenasCompartidas($xml) {
        $lista = [];
        foreach (self::dom($xml)->getElementsByTagNameNS(self::NS, 'si') as $si) {
            $lista[] = self::textoRico($si);
        }
        return $lista;
    }

    /** Texto de un <si> o <is>: une los fragmentos <t> y <r><t>, ignorando la fonética (<rPh>). */
    private static function textoRico(DOMElement $nodo) {
        $texto = '';
        foreach ($nodo->childNodes as $h) {
            if (!($h instanceof DOMElement)) { continue; }
            if ($h->localName === 't') {
                $texto .= $h->textContent;
            } elseif ($h->localName === 'r') {
                foreach ($h->childNodes as $t) {
                    if ($t instanceof DOMElement && $t->localName === 't') { $texto .= $t->textContent; }
                }
            }
        }
        return $texto;
    }

    private static function indiceColumna($ref) {
        if (!preg_match('/^([A-Z]+)/', strtoupper($ref), $m)) { return 0; }
        $n = 0;
        foreach (str_split($m[1]) as $ch) { $n = $n * 26 + (ord($ch) - 64); }
        return $n - 1;
    }

    private static function leerEntrada(ZipArchive $zip, $nombre) {
        $stat = $zip->statName($nombre);
        if ($stat === false) { return null; }
        if ($stat['size'] > self::MAX_BYTES_XML) {
            throw new RuntimeException('El archivo de Excel es demasiado grande. Guárdelo como CSV.');
        }
        $contenido = $zip->getFromName($nombre);
        return $contenido === false ? null : $contenido;
    }

    private static function dom($contenido) {
        // Sin DTD ni entidades: evita XXE y "billion laughs"
        if (stripos($contenido, '<!DOCTYPE') !== false || stripos($contenido, '<!ENTITY') !== false) {
            throw new RuntimeException('El archivo contiene XML no permitido.');
        }
        $previo = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $ok = $doc->loadXML($contenido, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        if (!$ok) {
            throw new RuntimeException('El archivo de Excel está dañado.');
        }
        return $doc;
    }

    // ---------------------------------------------------------------- escritura

    /** Neutraliza celdas que Excel interpretaría como fórmula (=, +, -, @, tab, retorno). */
    public static function neutralizar($valor) {
        $v = (string)$valor;
        if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false && !is_numeric($v)) {
            return "'" . $v;
        }
        return $v;
    }

    /** Envía un CSV UTF-8 (con BOM) a la salida. $filas incluye el encabezado como primera fila. */
    public static function escribirCsv(array $filas, $nombreArchivo) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($filas as $fila) {
            fputcsv($out, array_map([self::class, 'neutralizar'], $fila), ',', '"', '');
        }
        fclose($out);
    }

    /**
     * Genera un .xlsx (todas las celdas como texto) y lo devuelve como cadena binaria.
     * La primera fila se trata como encabezado (negrita y fila fija).
     */
    public static function xlsxComoCadena(array $filas, $nombreHoja = 'Datos') {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('El servidor no tiene habilitada la extensión zip.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de Excel.');
        }

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::esc(mb_substr($nombreHoja, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        // Estilo 0 = normal (texto @), estilo 1 = encabezado en negrita
        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="49" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            . '</cellXfs></styleSheet>');

        $anchos = [];
        foreach ($filas as $fila) {
            foreach (array_values($fila) as $i => $v) {
                $anchos[$i] = max($anchos[$i] ?? 8, min(60, mb_strlen((string)$v) + 2));
            }
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>';
        foreach ($anchos as $i => $w) {
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        foreach ($filas as $r => $fila) {
            $xml .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($fila) as $i => $v) {
                $v = (string)$v;
                if ($v === '') { continue; }
                $xml .= '<c r="' . self::nombreColumna($i) . ($r + 1) . '" t="inlineStr" s="' . ($r === 0 ? 1 : 0) . '"><is><t xml:space="preserve">'
                      . self::esc($v) . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        $bin = file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }

    public static function enviarXlsx(array $filas, $nombreArchivo, $nombreHoja = 'Datos') {
        $bin = self::xlsxComoCadena($filas, $nombreHoja);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . strlen($bin));
        header('X-Content-Type-Options: nosniff');
        echo $bin;
    }

    private static function nombreColumna($i) {
        $s = '';
        for ($n = $i + 1; $n > 0; $n = intdiv($n - 1, 26)) { $s = chr(65 + (($n - 1) % 26)) . $s; }
        return $s;
    }

    /** Escapa texto para XML y elimina caracteres de control no permitidos. */
    private static function esc($texto) {
        $texto = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string)$texto);
        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
