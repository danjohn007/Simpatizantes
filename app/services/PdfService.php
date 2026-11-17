<?php
/**
 * Generador mínimo de PDF (texto, rectángulos y líneas) sin librerías externas.
 *
 * Pensado para reportes sencillos que se generan en el servidor (cron, correo).
 * Hoja A4 vertical; coordenadas en milímetros con el origen arriba a la izquierda.
 * Fuentes estándar Helvetica / Helvetica-Bold (codificación Windows-1252: acentos y ñ).
 */
class PdfService {
    const ANCHO = 210;
    const ALTO = 297;

    private $paginas = [];   // contenido de cada página
    private $actual = -1;

    public function __construct() {
        $this->nuevaPagina();
    }

    public function nuevaPagina() {
        $this->paginas[] = '';
        $this->actual = count($this->paginas) - 1;
    }

    public function numeroPagina() { return $this->actual + 1; }

    private static function pt($mm) { return round($mm * 72 / 25.4, 2); }

    /** '#rrggbb' → "r g b" (0 a 1) */
    private static function rgb($hex) {
        $hex = ltrim($hex, '#');
        return sprintf('%.3f %.3f %.3f', hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255);
    }

    private static function texto($t) {
        $t = (string)$t;
        $w = function_exists('mb_convert_encoding') ? @mb_convert_encoding($t, 'Windows-1252', 'UTF-8') : utf8_decode($t);
        if ($w === false || $w === '') { $w = preg_replace('/[^\x20-\x7E]/', '?', $t); }
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $w);
    }

    private function agregar($op) { $this->paginas[$this->actual] .= $op . "\n"; }

    /** Texto con la esquina inferior izquierda de la línea base en ($x, $y). */
    public function texto_en($x, $y, $texto, $tam = 10, $negrita = false, $color = '#111111') {
        $this->agregar(sprintf('BT /%s %s Tf %s rg %s %s Td (%s) Tj ET',
            $negrita ? 'F2' : 'F1', $tam, self::rgb($color), self::pt($x), self::pt(self::ALTO - $y), self::texto($texto)));
    }

    /** Texto recortado a un máximo de caracteres (agrega "…" si se corta). */
    public function texto_corto($x, $y, $texto, $maxCaracteres, $tam = 10, $negrita = false, $color = '#111111') {
        $texto = (string)$texto;
        if (mb_strlen($texto) > $maxCaracteres) { $texto = mb_substr($texto, 0, max(1, $maxCaracteres - 1)) . '…'; }
        $this->texto_en($x, $y, $texto, $tam, $negrita, $color);
    }

    public function rect($x, $y, $w, $h, $relleno = '#111111') {
        if ($w <= 0 || $h <= 0) { return; }
        $this->agregar(sprintf('%s rg %s %s %s %s re f', self::rgb($relleno), self::pt($x), self::pt(self::ALTO - $y - $h), self::pt($w), self::pt($h)));
    }

    public function linea($x1, $y1, $x2, $y2, $color = '#d9d2cb', $grosor = 0.3) {
        $this->agregar(sprintf('%s RG %s w %s %s m %s %s l S', self::rgb($color), self::pt($grosor), self::pt($x1), self::pt(self::ALTO - $y1), self::pt($x2), self::pt(self::ALTO - $y2)));
    }

    /** Devuelve el PDF completo como cadena binaria. */
    public function salida($titulo = 'Reporte') {
        $objetos = [];
        $n = count($this->paginas);
        // 1 catálogo, 2 páginas, 3 y 4 fuentes, luego (página, contenido) por cada hoja
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $n; $i++) { $kids[] = (5 + $i * 2) . ' 0 R'; }
        $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        for ($i = 0; $i < $n; $i++) {
            $pagina = 5 + $i * 2;
            $contenido = $pagina + 1;
            $objetos[$pagina] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::pt(self::ANCHO), self::pt(self::ALTO), $contenido);
            $objetos[$contenido] = '<< /Length ' . strlen($this->paginas[$i]) . " >>\nstream\n" . $this->paginas[$i] . 'endstream';
        }
        $info = count($objetos) + 1;
        $objetos[$info] = '<< /Title (' . self::texto($titulo) . ') /Producer (Sistema de Validación de Simpatizantes) /CreationDate (D:' . date('YmdHis') . ') >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objetos as $num => $cuerpo) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $cuerpo . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . ($info + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $info; $i++) { $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]); }
        $pdf .= "trailer\n<< /Size " . ($info + 1) . " /Root 1 0 R /Info $info 0 R >>\nstartxref\n$xref\n%%EOF\n";
        return $pdf;
    }
}
