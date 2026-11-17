<?php
/**
 * Reporte semanal de captación: PDF de dos páginas + resumen en HTML para el cuerpo del correo.
 *
 * generar(): arma el reporte de una campaña (o de todas si campana_id es null) para los 7 días
 * que terminan en $hasta, comparado con los 7 anteriores.
 */

require_once __DIR__ . '/AnaliticaService.php';
require_once __DIR__ . '/PdfService.php';
require_once __DIR__ . '/../models/Campo.php';
require_once __DIR__ . '/EmailService.php';

class ReporteSemanalService {
    const DIAS = 7;

    /**
     * @param int|null $campanaId   null = todas las campañas
     * @param string   $campanaNombre nombre para el encabezado
     * @param string   $hasta       Y-m-d (último día del periodo)
     * @return array ['pdf' => binario, 'html' => cuerpo del correo, 'asunto' => texto, 'datos' => arreglo con las cifras]
     */
    public static function generar($campanaId, $campanaNombre, $hasta) {
        $fin = new DateTime($hasta);
        $ini = (clone $fin)->modify('-' . (self::DIAS - 1) . ' days');
        $f = ['fecha_inicio' => $ini->format('Y-m-d'), 'fecha_fin' => $fin->format('Y-m-d')];
        if ($campanaId) { $f['campana_id'] = (int)$campanaId; }

        $a = new AnaliticaService();
        $comp = $a->comparativo($f);
        $act = $comp['actual'];
        $serie = AnaliticaService::serieDiaria($a->porDia($f), $f['fecha_inicio'], $f['fecha_fin']);
        $secciones = $a->porSeccion($f, 8);
        $ciudades = $a->porCiudad($f, 8);
        $capturistas = $a->porCapturista($f, 15);
        $estatus = $a->estatus($f);
        $metas = $campanaId ? (new Campo())->metas(['campana_id' => (int)$campanaId, 'vigentes' => 1]) : [];

        $periodo = $ini->format('d/m/Y') . ' al ' . $fin->format('d/m/Y');
        $titulo = $campanaNombre !== '' ? $campanaNombre : 'Todas las campañas';
        $datos = compact('f', 'comp', 'serie', 'secciones', 'ciudades', 'capturistas', 'estatus', 'metas', 'periodo', 'titulo');

        return [
            'pdf' => self::pdf($datos),
            'html' => self::html($datos),
            'asunto' => 'Reporte semanal · ' . $titulo . ' · ' . $periodo,
            'datos' => $datos,
        ];
    }

    private static function variacion($v) {
        if ($v === null) { return ['sin comparativo', '#6e655e']; }
        return [($v > 0 ? '+' : '') . number_format($v, 1) . '% vs semana anterior', $v >= 0 ? '#2f6b4a' : '#8a2f25'];
    }

    // ------------------------------------------------------------------ PDF

    private static function pdf(array $d) {
        $pdf = new PdfService();
        $naranja = '#ff8000'; $negro = '#111111'; $gris = '#6e655e'; $fondo = '#f5f2ee';
        $act = $d['comp']['actual'];

        $encabezado = function () use ($pdf, $d, $naranja) {
            $pdf->rect(0, 0, 210, 28, '#111111');
            $pdf->rect(0, 28, 210, 1.6, '#ff8000');
            $pdf->texto_en(15, 13.5, 'Reporte semanal de captación', 17, true, '#ffffff');
            $pdf->texto_corto(15, 21.5, $d['titulo'] . '  ·  ' . $d['periodo'], 90, 10, false, '#f0e6da');
        };
        $pie = function () use ($pdf, $gris) {
            $pdf->linea(15, 284, 195, 284);
            $pdf->texto_en(15, 289, 'Generado el ' . date('d/m/Y H:i') . '  ·  ' . (defined('APP_NAME') ? APP_NAME : 'Sistema de Validación de Simpatizantes'), 7.5, false, $gris);
            $pdf->texto_en(186, 289, 'Pág. ' . $pdf->numeroPagina(), 7.5, false, $gris);
        };

        // ---------------- Página 1
        $encabezado();
        [$txtVar, $colVar] = self::variacion($d['comp']['var_total']);
        [$txtVarV, $colVarV] = self::variacion($d['comp']['var_validados']);
        $kpis = [
            ['Registros', number_format($act['total']), $txtVar, $colVar],
            ['Validados', number_format($act['validados']), $act['pct_validados'] . '% del total', $gris],
            ['Secciones con registros', number_format($act['secciones']), 'de la semana', $gris],
            ['Capturistas activos', number_format($act['capturistas']), 'de la semana', $gris],
        ];
        foreach ($kpis as $i => $k) {
            $x = 15 + $i * 45.5;
            $pdf->rect($x, 38, 42, 25, $fondo);
            $pdf->rect($x, 38, 1.2, 25, $naranja);
            $pdf->texto_corto($x + 4, 45, strtoupper($k[0]), 24, 6.5, true, $gris);
            $pdf->texto_en($x + 4, 54, $k[1], 18, true, $negro);
            $pdf->texto_corto($x + 4, 60, $k[2], 26, 7, false, $k[3]);
        }

        // Registros por día
        $pdf->texto_en(15, 76, 'Registros por día', 11, true, $negro);
        $max = max(1, max(array_column($d['serie'], 'total')));
        $n = count($d['serie']);
        $ancho = 180 / max(1, $n);
        $base = 118; $altoMax = 32;
        $pdf->linea(15, $base, 195, $base, '#d9d2cb', 0.3);
        foreach ($d['serie'] as $i => $dia) {
            $h = $dia['total'] > 0 ? max(0.8, $altoMax * $dia['total'] / $max) : 0;
            $x = 15 + $i * $ancho + $ancho * 0.18;
            $pdf->rect($x, $base - $h, $ancho * 0.64, $h, $naranja);
            $pdf->texto_en($x + ($ancho * 0.32) - (strlen((string)$dia['total']) * 0.9), $base - $h - 1.5, (string)$dia['total'], 8, true, $negro);
            $pdf->texto_en($x + 1, $base + 5, date('d/m', strtotime($dia['fecha'])), 7.5, false, $gris);
        }

        // Secciones y municipios (barras horizontales)
        $barras = function ($x0, $titulo, $filas, $campoEtq, $prefijo) use ($pdf, $negro, $gris, $naranja) {
            $pdf->texto_en($x0, 136, $titulo, 11, true, $negro);
            if (!$filas) { $pdf->texto_en($x0, 145, 'Sin registros en el periodo.', 9, false, $gris); return; }
            $m = max(1, (int)$filas[0]['total']);
            foreach ($filas as $i => $r) {
                $y = 145 + $i * 7;
                $pdf->texto_corto($x0, $y, $prefijo . $r[$campoEtq], 18, 8.5, false, $negro);
                $pdf->rect($x0 + 33, $y - 3.2, max(0.8, 38 * $r['total'] / $m), 3.8, $naranja);
                $pdf->texto_en($x0 + 33 + max(0.8, 38 * $r['total'] / $m) + 1.5, $y, number_format($r['total']), 8, true, $negro);
            }
        };
        $barras(15, 'Secciones con más registros', $d['secciones'], 'seccion_electoral', 'Sección ');
        $barras(110, 'Municipios con más registros', $d['ciudades'], 'ciudad', '');

        // Estado de validación de lo capturado
        $pdf->texto_en(15, 212, 'Estado de validación (registros de la semana)', 11, true, $negro);
        $tot = max(1, array_sum($d['estatus']));
        $x = 15;
        foreach ([['validado', 'Validados', '#2f6b4a'], ['pendiente', 'Pendientes', '#ff8000'], ['rechazado', 'Rechazados', '#8a2f25']] as $e) {
            $w = 180 * ($d['estatus'][$e[0]] ?? 0) / $tot;
            $pdf->rect($x, 218, $w, 7, $e[2]);
            $x += $w;
        }
        foreach ([['validado', 'Validados', '#2f6b4a'], ['pendiente', 'Pendientes', '#ff8000'], ['rechazado', 'Rechazados', '#8a2f25']] as $i => $e) {
            $pdf->rect(15 + $i * 60, 230, 3, 3, $e[2]);
            $pdf->texto_en(20 + $i * 60, 232.8, $e[1] . ': ' . number_format($d['estatus'][$e[0]] ?? 0) . ' (' . round(100 * ($d['estatus'][$e[0]] ?? 0) / $tot) . '%)', 8.5, false, $negro);
        }
        $pdf->texto_en(15, 248, 'Periodo anterior para comparar: ' . date('d/m/Y', strtotime($d['comp']['periodo_anterior'][0])) . ' al ' . date('d/m/Y', strtotime($d['comp']['periodo_anterior'][1]))
            . ' (' . number_format($d['comp']['anterior']['total']) . ' registros).', 8.5, false, $gris);
        $pie();

        // ---------------- Página 2
        $pdf->nuevaPagina();
        $encabezado();
        $pdf->texto_en(15, 42, 'Capturistas de la semana', 11, true, $negro);
        $cols = [[15, 'Capturista'], [105, 'Registros'], [128, 'Validados'], [152, '% valid.'], [172, 'Última captura']];
        $pdf->rect(15, 46, 180, 7, $fondo);
        foreach ($cols as $c) { $pdf->texto_en($c[0] + 1.5, 51, $c[1], 8, true, $gris); }
        if (!$d['capturistas']) {
            $pdf->texto_en(16, 61, 'Sin capturas en el periodo.', 9, false, $gris);
        }
        foreach ($d['capturistas'] as $i => $c) {
            $y = 60 + $i * 7;
            if ($i % 2 === 1) { $pdf->rect(15, $y - 4.6, 180, 7, '#faf8f5'); }
            $pdf->texto_corto(16.5, $y, $c['nombre_completo'], 42, 9, false, $negro);
            $pdf->texto_en(106.5, $y, number_format($c['total']), 9, true, $negro);
            $pdf->texto_en(129.5, $y, number_format($c['validados']), 9, false, $negro);
            $pdf->texto_en(153.5, $y, $c['pct'] . '%', 9, false, $negro);
            $pdf->texto_en(173.5, $y, $c['ultimo'] ? date('d/m H:i', strtotime($c['ultimo'])) : '—', 8.5, false, $gris);
        }

        $y0 = 60 + max(1, count($d['capturistas'])) * 7 + 12;
        $pdf->texto_en(15, $y0, 'Metas vigentes', 11, true, $negro);
        if (!$d['metas']) {
            $pdf->texto_en(15, $y0 + 9, 'No hay metas vigentes' . ($d['f']['campana_id'] ?? null ? ' para esta campaña.' : ' (el detalle de metas se muestra por campaña).'), 9, false, $gris);
        }
        foreach (array_slice($d['metas'], 0, 8) as $i => $m) {
            $y = $y0 + 11 + $i * 11;
            if ($y > 275) { break; }
            $obj = max(1, (int)$m['objetivo']); $reg = (int)$m['registrados']; $pct = min(100, round($reg * 100 / $obj));
            $alcance = $m['usuario_id'] ? 'Capturista: ' . $m['capturista_nombre'] : ($m['seccion_electoral'] ? 'Sección ' . $m['seccion_electoral'] : 'Toda la campaña');
            $pdf->texto_corto(15, $y, $alcance, 48, 9, false, $negro);
            $pdf->rect(78, $y - 3.6, 80, 4.4, '#ece6df');
            $pdf->rect(78, $y - 3.6, 80 * $pct / 100, 4.4, $pct >= 100 ? '#2f6b4a' : '#ff8000');
            $pdf->texto_en(161, $y, number_format($reg) . ' / ' . number_format($obj) . '  (' . $pct . '%)', 8.5, true, $negro);
            $pdf->texto_en(78, $y + 5, 'Hasta el ' . date('d/m/Y', strtotime($m['fecha_fin'])), 7, false, $gris);
        }
        $pie();

        return $pdf->salida('Reporte semanal · ' . $d['titulo']);
    }

    // ----------------------------------------------------------------- HTML (correo)

    private static function html(array $d) {
        $e = function ($t) { return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'); };
        $act = $d['comp']['actual'];
        [$txtVar, $colVar] = self::variacion($d['comp']['var_total']);
        $top = '';
        foreach (array_slice($d['secciones'], 0, 5) as $s) {
            $top .= '<tr><td style="padding:4px 0">Sección ' . $e($s['seccion_electoral']) . '</td><td style="padding:4px 0;text-align:right"><b>' . number_format($s['total']) . '</b></td></tr>';
        }
        $cap = '';
        foreach (array_slice($d['capturistas'], 0, 5) as $c) {
            $cap .= '<tr><td style="padding:4px 0">' . $e($c['nombre_completo']) . '</td><td style="padding:4px 0;text-align:right"><b>' . number_format($c['total']) . '</b> <span style="color:#6e655e">(' . $c['pct'] . '% validados)</span></td></tr>';
        }
        return '<div style="font-family:Arial,Helvetica,sans-serif;color:#111;max-width:560px;margin:0 auto">'
            . '<div style="background:#111;color:#fff;padding:18px 22px;border-bottom:4px solid #ff8000"><div style="font-size:18px;font-weight:bold">Reporte semanal de captación</div>'
            . '<div style="font-size:13px;color:#f0e6da;margin-top:4px">' . $e($d['titulo']) . ' · ' . $e($d['periodo']) . '</div></div>'
            . '<div style="padding:20px 22px;background:#fff;border:1px solid #e9e4de;border-top:0">'
            . '<p style="font-size:30px;font-weight:bold;margin:0">' . number_format($act['total']) . ' <span style="font-size:14px;font-weight:normal">registros nuevos</span></p>'
            . '<p style="margin:2px 0 14px;color:' . $colVar . ';font-weight:bold;font-size:13px">' . $e($txtVar) . '</p>'
            . '<p style="margin:0 0 16px;font-size:14px">' . number_format($act['validados']) . ' validados (' . $act['pct_validados'] . '%) · ' . number_format($act['secciones']) . ' secciones · ' . number_format($act['capturistas']) . ' capturistas activos.</p>'
            . ($top ? '<p style="font-weight:bold;margin:0 0 4px">Secciones con más registros</p><table style="width:100%;font-size:14px;border-collapse:collapse">' . $top . '</table>' : '')
            . ($cap ? '<p style="font-weight:bold;margin:16px 0 4px">Capturistas</p><table style="width:100%;font-size:14px;border-collapse:collapse">' . $cap . '</table>' : '')
            . '<p style="margin:18px 0 0;font-size:13px;color:#6e655e">El detalle completo va en el PDF adjunto.</p></div></div>';
    }

    // ------------------------------------------------------------------ envío

    /**
     * Quién recibe qué: cada coordinador o candidato, el reporte de su campaña;
     * los administradores, uno consolidado de todas las campañas.
     * @return array [ ['campana_id' => int|null, 'nombre' => texto, 'usuarios' => [ ['email' =>, 'nombre' =>], … ]], … ]
     */
    public static function grupos() {
        $db = Database::getInstance();
        $valido = function ($u) { return filter_var($u['email'] ?? '', FILTER_VALIDATE_EMAIL); };
        $grupos = [];

        foreach ($db->query("SELECT id, nombre FROM campanas WHERE activa = 1 ORDER BY nombre") ?: [] as $c) {
            $us = $db->query(
                "SELECT nombre_completo AS nombre, email FROM usuarios
                 WHERE rol IN ('coordinador', 'candidato') AND campana_id = ? AND (activo = 1 OR activo IS NULL)",
                [$c['id']]
            ) ?: [];
            $us = array_values(array_filter($us, $valido));
            if ($us) { $grupos[] = ['campana_id' => (int)$c['id'], 'nombre' => $c['nombre'], 'usuarios' => $us]; }
        }

        $admins = $db->query(
            "SELECT nombre_completo AS nombre, email FROM usuarios WHERE rol IN ('super_admin', 'admin') AND (activo = 1 OR activo IS NULL)"
        ) ?: [];
        $admins = array_values(array_filter($admins, $valido));
        if ($admins) { $grupos[] = ['campana_id' => null, 'nombre' => 'Todas las campañas', 'usuarios' => $admins]; }

        return $grupos;
    }

    /**
     * Genera el reporte de un grupo y lo manda a todos sus destinatarios.
     * @return array ['enviados' => n, 'fallidos' => [correos]]
     */
    public static function enviarGrupo(array $grupo, $hasta, EmailService $correo = null) {
        $correo = $correo ?: new EmailService();
        $r = self::generar($grupo['campana_id'], $grupo['nombre'], $hasta);
        $archivo = 'reporte_semanal_' . date('Y-m-d', strtotime($hasta)) . '.pdf';
        $enviados = 0;
        $fallidos = [];
        foreach ($grupo['usuarios'] as $u) {
            if ($correo->sendEmailWithAttachment($u['email'], $r['asunto'], $r['html'], $archivo, $r['pdf'], 'application/pdf', $u['nombre'])) {
                $enviados++;
            } else {
                $fallidos[] = $u['email'];
            }
        }
        return ['enviados' => $enviados, 'fallidos' => $fallidos];
    }
}
