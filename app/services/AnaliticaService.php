<?php
/**
 * Consultas de análisis (tablero y reportes) con los mismos filtros que el listado.
 *
 * Filtros: campana_id, capturista_id, seccion_electoral, ciudad, estatus, fecha_inicio, fecha_fin.
 * Todas las consultas pasan por una caché corta que se invalida al cambiar los simpatizantes.
 */

require_once __DIR__ . '/../models/Simpatizante.php';
require_once __DIR__ . '/CacheService.php';

class AnaliticaService {
    private $modelo;
    private $db;

    public function __construct() {
        $this->modelo = new Simpatizante();
        $this->db = Database::getInstance();
    }

    /**
     * Ajusta los filtros al alcance del usuario: quien no ve todas las campañas queda en la suya,
     * y un capturista solo ve lo que él capturó.
     */
    public static function alcance($auth, array $filtros) {
        if (!$auth->puedeVerTodasLasCampanas()) {
            $filtros['campana_id'] = (int)$auth->obtenerCampanaId() ?: -1; // -1: sin campaña, no devuelve nada
        }
        if ($auth->obtenerRol() === 'capturista') {
            $filtros['capturista_id'] = (int)$auth->obtenerUsuarioId();
        }
        return $filtros;
    }

    private function dato($nombre, array $filtros, callable $calcular) {
        ksort($filtros);
        return CacheService::recordar($nombre . '|' . json_encode($filtros), $calcular);
    }

    /** Totales del conjunto filtrado. */
    public function resumen(array $f) {
        return $this->dato('resumen', $f, function () use ($f) {
            [$w, $p] = $this->modelo->condiciones($f);
            $r = $this->db->queryOne(
                "SELECT COUNT(*) AS total,
                        COALESCE(SUM(s.validado = 1), 0) AS validados,
                        COUNT(DISTINCT s.seccion_electoral) AS secciones,
                        COUNT(DISTINCT s.capturista_id) AS capturistas,
                        COALESCE(SUM(s.latitud IS NOT NULL AND s.longitud IS NOT NULL), 0) AS con_ubicacion
                 FROM simpatizantes s $w", $p
            ) ?: [];
            $t = (int)($r['total'] ?? 0);
            $v = (int)($r['validados'] ?? 0);
            return [
                'total' => $t, 'validados' => $v, 'secciones' => (int)($r['secciones'] ?? 0),
                'capturistas' => (int)($r['capturistas'] ?? 0), 'con_ubicacion' => (int)($r['con_ubicacion'] ?? 0),
                'pct_validados' => $t > 0 ? round($v * 100 / $t, 1) : 0,
            ];
        });
    }

    /** Conteo por estado de validación (pendiente / validado / rechazado). */
    public function estatus(array $f) {
        return $this->dato('estatus', $f, function () use ($f) { return $this->modelo->contarPorEstatus($f); });
    }

    /**
     * Periodo actual frente al anterior de la misma duración (p. ej. 7 días vs los 7 anteriores).
     * Requiere fecha_inicio y fecha_fin en los filtros.
     */
    public function comparativo(array $f) {
        $ini = new DateTime($f['fecha_inicio']);
        $fin = new DateTime($f['fecha_fin']);
        $dias = (int)$ini->diff($fin)->days + 1;
        $prevFin = (clone $ini)->modify('-1 day');
        $prevIni = (clone $prevFin)->modify('-' . ($dias - 1) . ' days');

        $anterior = $f;
        $anterior['fecha_inicio'] = $prevIni->format('Y-m-d');
        $anterior['fecha_fin'] = $prevFin->format('Y-m-d');
        $a = $this->resumen($f);
        $b = $this->resumen($anterior);

        $var = function ($x, $y) { return $y > 0 ? round(($x - $y) * 100 / $y, 1) : null; };
        return [
            'dias' => $dias, 'actual' => $a, 'anterior' => $b,
            'periodo_anterior' => [$anterior['fecha_inicio'], $anterior['fecha_fin']],
            'var_total' => $var($a['total'], $b['total']),
            'var_validados' => $var($a['validados'], $b['validados']),
        ];
    }

    /** Registros por día: [['fecha' => 'Y-m-d', 'total' => n], …] (solo los días con registros). */
    public function porDia(array $f) {
        return $this->dato('porDia', $f, function () use ($f) {
            [$w, $p] = $this->modelo->condiciones($f);
            return $this->db->query(
                "SELECT DATE(s.created_at) AS fecha, COUNT(*) AS total FROM simpatizantes s $w
                 GROUP BY DATE(s.created_at) ORDER BY fecha ASC", $p
            ) ?: [];
        });
    }

    public function porSeccion(array $f, $limite = 0) {
        return $this->dato('porSeccion' . (int)$limite, $f, function () use ($f, $limite) {
            [$w, $p] = $this->modelo->condiciones($f);
            return $this->db->query(
                "SELECT s.seccion_electoral, COUNT(*) AS total FROM simpatizantes s $w
                 GROUP BY s.seccion_electoral ORDER BY total DESC, s.seccion_electoral"
                . ($limite > 0 ? ' LIMIT ' . (int)$limite : ''), $p
            ) ?: [];
        });
    }

    /** Municipios / ciudades con más registros. */
    public function porCiudad(array $f, $limite = 10) {
        return $this->dato('porCiudad' . (int)$limite, $f, function () use ($f, $limite) {
            [$w, $p] = $this->modelo->condiciones($f);
            $w .= ($w === '' ? 'WHERE ' : ' AND ') . "s.ciudad IS NOT NULL AND s.ciudad <> ''";
            return $this->db->query(
                "SELECT s.ciudad, COUNT(*) AS total FROM simpatizantes s $w
                 GROUP BY s.ciudad ORDER BY total DESC, s.ciudad LIMIT " . (int)$limite, $p
            ) ?: [];
        });
    }

    /** Temas que más piden las personas registradas: [['tema' => clave, 'etiqueta' => texto, 'total' => n, 'pct' => %]]. */
    public function porTema(array $f) {
        return $this->dato('porTema', $f, function () use ($f) {
            if (!$this->modelo->tieneTemas()) { return []; }
            [$w, $p] = $this->modelo->condiciones($f);
            $filas = $this->db->query(
                "SELECT t.tema, COUNT(DISTINCT t.simpatizante_id) AS total
                 FROM simpatizante_temas t INNER JOIN simpatizantes s ON s.id = t.simpatizante_id $w
                 GROUP BY t.tema ORDER BY total DESC", $p
            ) ?: [];
            $conTemas = $this->db->queryOne(
                "SELECT COUNT(DISTINCT t.simpatizante_id) AS n FROM simpatizante_temas t INNER JOIN simpatizantes s ON s.id = t.simpatizante_id $w", $p
            );
            $base = max(1, (int)($conTemas['n'] ?? 0));
            foreach ($filas as &$r) {
                $r['etiqueta'] = Simpatizante::TEMAS[$r['tema']] ?? $r['tema'];
                $r['total'] = (int)$r['total'];
                $r['pct'] = round($r['total'] * 100 / $base);
            }
            return $filas;
        });
    }

    /** Ranking de capturistas: registros, validados, % de validación y última captura. */
    public function porCapturista(array $f, $limite = 10) {
        return $this->dato('porCapturista' . (int)$limite, $f, function () use ($f, $limite) {
            [$w, $p] = $this->modelo->condiciones($f);
            $filas = $this->db->query(
                "SELECT u.id, u.nombre_completo, COUNT(s.id) AS total,
                        COALESCE(SUM(s.validado = 1), 0) AS validados, MAX(s.created_at) AS ultimo
                 FROM simpatizantes s INNER JOIN usuarios u ON u.id = s.capturista_id $w
                 GROUP BY u.id, u.nombre_completo ORDER BY total DESC, u.nombre_completo LIMIT " . (int)$limite, $p
            ) ?: [];
            foreach ($filas as &$r) {
                $r['total'] = (int)$r['total'];
                $r['validados'] = (int)$r['validados'];
                $r['pct'] = $r['total'] > 0 ? round($r['validados'] * 100 / $r['total'], 1) : 0;
            }
            return $filas;
        });
    }

    /** Ciudades disponibles para el filtro (sin filtros de fecha ni estado). */
    public function ciudades(array $f) {
        $base = array_intersect_key($f, array_flip(['campana_id', 'capturista_id']));
        return array_column($this->porCiudad($base, 200), 'ciudad');
    }

    /** Rellena los días sin registros para graficar series continuas. */
    public static function serieDiaria(array $porDia, $inicio, $fin) {
        $mapa = [];
        foreach ($porDia as $r) { $mapa[$r['fecha']] = (int)$r['total']; }
        $serie = [];
        for ($d = new DateTime($inicio), $e = new DateTime($fin); $d <= $e; $d->modify('+1 day')) {
            $k = $d->format('Y-m-d');
            $serie[] = ['fecha' => $k, 'total' => $mapa[$k] ?? 0];
        }
        return $serie;
    }

    /** Lee y valida los filtros comunes de una petición GET. */
    public static function filtrosDeGet(array $get, $diasPorDefecto = 30) {
        $esFecha = function ($v) { $d = DateTime::createFromFormat('!Y-m-d', (string)$v); return $d && $d->format('Y-m-d') === $v; };
        $f = [];
        $f['fecha_fin'] = (isset($get['fecha_fin']) && $esFecha($get['fecha_fin'])) ? $get['fecha_fin'] : date('Y-m-d');
        $f['fecha_inicio'] = (isset($get['fecha_inicio']) && $esFecha($get['fecha_inicio']))
            ? $get['fecha_inicio'] : date('Y-m-d', strtotime($f['fecha_fin'] . " -" . ($diasPorDefecto - 1) . " days"));
        if ($f['fecha_inicio'] > $f['fecha_fin']) { [$f['fecha_inicio'], $f['fecha_fin']] = [$f['fecha_fin'], $f['fecha_inicio']]; }
        if (!empty($get['campana_id']) && ctype_digit((string)$get['campana_id'])) { $f['campana_id'] = (int)$get['campana_id']; }
        if (!empty($get['ciudad']) && is_string($get['ciudad'])) { $f['ciudad'] = mb_substr($get['ciudad'], 0, 100); }
        if (!empty($get['seccion']) && is_string($get['seccion']) && preg_match('/^\d{1,4}$/', $get['seccion'])) { $f['seccion_electoral'] = str_pad($get['seccion'], 4, '0', STR_PAD_LEFT); }
        if (!empty($get['estatus']) && in_array($get['estatus'], ['pendiente', 'validado', 'rechazado'], true)) { $f['estatus'] = $get['estatus']; }
        return $f;
    }
}
