<?php
/**
 * Comunicación con simpatizantes: segmentos, plantillas, enlaces de WhatsApp y correo masivo.
 *
 * Reglas de consentimiento:
 *  - El correo masivo SOLO sale a quien aceptó recibir mensajes (consentimiento = 1) y no se dio de baja.
 *  - WhatsApp es 1 a 1 y lo envía una persona desde su teléfono; se bloquea para quien pidió baja o dijo
 *    que no, y se advierte cuando no hay consentimiento registrado.
 */

require_once __DIR__ . '/../models/Simpatizante.php';
require_once __DIR__ . '/../models/Contacto.php';
require_once __DIR__ . '/EmailQueue.php';

class ComunicacionService {
    const MAX_CORREO_POR_ENVIO = 200;

    private $db;
    private $modelo;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->modelo = new Simpatizante();
    }

    // ------------------------------------------------------------------ segmentos

    /** Filtros propios de la comunicación, además de los del listado (campaña, sección, ciudad, estado…). */
    private function condiciones(array $f) {
        [$w, $p] = $this->modelo->condiciones($f);
        $extra = [];
        switch ($f['consentimiento'] ?? '') {
            case 'si':            $extra[] = "s.consentimiento = 1 AND s.baja_comunicacion = 0"; break;
            case 'sin_registrar': $extra[] = "s.consentimiento IS NULL AND s.baja_comunicacion = 0"; break;
            case 'baja':          $extra[] = "(s.baja_comunicacion = 1 OR s.consentimiento = 0)"; break;
        }
        if (!empty($f['sin_contacto_dias']) && (int)$f['sin_contacto_dias'] > 0) {
            $extra[] = "(s.ultimo_contacto IS NULL OR s.ultimo_contacto < DATE_SUB(NOW(), INTERVAL " . (int)$f['sin_contacto_dias'] . " DAY))";
        }
        if (!empty($f['con_whatsapp'])) { $extra[] = "s.whatsapp IS NOT NULL AND s.whatsapp <> ''"; }
        if (!empty($f['con_correo']))   { $extra[] = "s.email IS NOT NULL AND s.email <> ''"; }
        if ($extra) { $w .= ($w === '' ? 'WHERE ' : ' AND ') . implode(' AND ', $extra); }
        return [$w, $p];
    }

    public function contar(array $f) {
        [$w, $p] = $this->condiciones($f);
        $r = $this->db->queryOne("SELECT COUNT(*) AS n FROM simpatizantes s $w", $p);
        return (int)($r['n'] ?? 0);
    }

    /** Página del segmento: primero quienes nunca han sido contactados, luego los más antiguos. */
    public function segmento(array $f, $pagina = 1, $porPagina = 25) {
        [$w, $p] = $this->condiciones($f);
        return $this->db->query(
            "SELECT s.id, s.nombre_completo, s.seccion_electoral, s.ciudad, s.telefono, s.whatsapp, s.email,
                    s.consentimiento, s.baja_comunicacion, s.ultimo_contacto, s.campana_id,
                    c.nombre AS campana_nombre, u.nombre_completo AS capturista_nombre
             FROM simpatizantes s
             LEFT JOIN campanas c ON c.id = s.campana_id
             LEFT JOIN usuarios u ON u.id = s.capturista_id
             $w
             ORDER BY (s.ultimo_contacto IS NULL) DESC, s.ultimo_contacto ASC, s.id DESC
             LIMIT " . (int)$porPagina . " OFFSET " . (int)(max(1, $pagina) - 1) * (int)$porPagina,
            $p
        ) ?: [];
    }

    /** Segmento completo (hasta $max) para exportar. */
    public function segmentoCompleto(array $f, $max = 20000) {
        [$w, $p] = $this->condiciones($f);
        return $this->db->query(
            "SELECT s.id, s.nombre_completo, s.seccion_electoral, s.ciudad, s.telefono, s.whatsapp, s.email,
                    s.consentimiento, s.baja_comunicacion, s.ultimo_contacto, c.nombre AS campana_nombre
             FROM simpatizantes s LEFT JOIN campanas c ON c.id = s.campana_id
             $w ORDER BY s.seccion_electoral, s.nombre_completo LIMIT " . (int)$max, $p
        ) ?: [];
    }

    // ------------------------------------------------------------------ plantillas

    public function plantillas($canal = null) {
        $sql = "SELECT * FROM plantillas_mensaje" . ($canal ? " WHERE canal = ?" : "") . " ORDER BY nombre, canal";
        return $this->db->query($sql, $canal ? [$canal] : []) ?: [];
    }

    public function plantilla($clave) {
        $r = $this->db->queryOne("SELECT * FROM plantillas_mensaje WHERE clave = ?", [$clave]);
        return $r ?: null;
    }

    /** @return array ['success'=>true] o ['error'=>texto] */
    public function guardarPlantilla($clave, $asunto, $cuerpo, $usuarioId) {
        $p = $this->plantilla($clave);
        if (!$p) { return ['error' => 'La plantilla no existe']; }
        $cuerpo = trim((string)$cuerpo);
        $asunto = trim((string)$asunto);
        if ($cuerpo === '') { return ['error' => 'El mensaje no puede estar vacío']; }
        if (mb_strlen($cuerpo) > 4000) { return ['error' => 'El mensaje no puede pasar de 4,000 caracteres']; }
        if ($p['canal'] === 'correo') {
            if ($asunto === '') { return ['error' => 'El asunto del correo es obligatorio']; }
            if (mb_strlen($asunto) > 150) { return ['error' => 'El asunto no puede pasar de 150 caracteres']; }
            if (strpos($cuerpo, '{{enlace_baja}}') === false) { return ['error' => 'Los correos deben incluir {{enlace_baja}} para que la persona pueda darse de baja']; }
        } else {
            $asunto = '';
        }
        $ok = $this->db->execute("UPDATE plantillas_mensaje SET asunto = ?, cuerpo = ?, updated_by = ? WHERE clave = ?",
            [$asunto !== '' ? $asunto : null, $cuerpo, $usuarioId, $clave]);
        return $ok ? ['success' => true] : ['error' => $this->db->describirError('guardar la plantilla')];
    }

    /** Variables que se pueden usar dentro de las plantillas. */
    public static function variables() {
        return [
            '{{nombre}}' => 'Nombre completo', '{{primer_nombre}}' => 'Primer nombre', '{{seccion}}' => 'Sección electoral',
            '{{ciudad}}' => 'Municipio', '{{campana}}' => 'Nombre de la campaña', '{{capturista}}' => 'Quien envía el mensaje',
            '{{enlace_baja}}' => 'Enlace para darse de baja (solo correo)',
        ];
    }

    /** Sustituye las variables {{…}} por los datos del simpatizante. Devuelve texto plano. */
    public static function render($texto, array $simp, array $extra = []) {
        $nombre = trim((string)($simp['nombre_completo'] ?? ''));
        $primer = $nombre !== '' ? preg_split('/\s+/u', $nombre)[0] : '';
        $primer = mb_convert_case(mb_strtolower($primer), MB_CASE_TITLE);
        $valores = [
            '{{nombre}}' => $nombre,
            '{{primer_nombre}}' => $primer,
            '{{seccion}}' => (string)($simp['seccion_electoral'] ?? ''),
            '{{ciudad}}' => (string)($simp['ciudad'] ?? '') !== '' ? (string)$simp['ciudad'] : 'tu zona',
            '{{campana}}' => (string)($simp['campana_nombre'] ?? ($extra['campana'] ?? 'nuestro equipo')),
            '{{capturista}}' => (string)($extra['capturista'] ?? ($simp['capturista_nombre'] ?? 'tu equipo de campaña')),
            '{{enlace_baja}}' => (string)($extra['enlace_baja'] ?? ''),
        ];
        return strtr((string)$texto, $valores);
    }

    // ------------------------------------------------------------------ WhatsApp

    /** Enlace wa.me con el mensaje escrito; null si la persona no tiene un número de 10 dígitos. */
    public static function enlaceWhatsapp(array $simp, $mensaje) {
        foreach (['whatsapp', 'telefono'] as $campo) {
            $n = preg_replace('/\D+/', '', (string)($simp[$campo] ?? ''));
            if (strlen($n) === 10) {
                return 'https://wa.me/52' . $n . '?text=' . rawurlencode($mensaje);
            }
        }
        return null;
    }

    /** ¿Se puede escribir por WhatsApp? ['ok' => bool, 'aviso' => texto|null] */
    public static function estadoWhatsapp(array $simp) {
        if (!empty($simp['baja_comunicacion'])) { return ['ok' => false, 'aviso' => 'Pidió no recibir mensajes']; }
        if (isset($simp['consentimiento']) && $simp['consentimiento'] !== null && (int)$simp['consentimiento'] === 0) { return ['ok' => false, 'aviso' => 'No aceptó recibir mensajes']; }
        if (!isset($simp['consentimiento']) || $simp['consentimiento'] === null) { return ['ok' => true, 'aviso' => 'Sin consentimiento registrado']; }
        return ['ok' => true, 'aviso' => null];
    }

    // ------------------------------------------------------------------ correo

    /** Enlace personal para darse de baja (crea el token la primera vez). */
    public function urlBaja($simpatizanteId) {
        $r = $this->db->queryOne("SELECT baja_token FROM simpatizantes WHERE id = ?", [(int)$simpatizanteId]);
        $token = $r['baja_token'] ?? null;
        if (!$token) {
            $token = bin2hex(random_bytes(16));
            $this->db->execute("UPDATE simpatizantes SET baja_token = ? WHERE id = ? AND baja_token IS NULL", [$token, (int)$simpatizanteId]);
            $r = $this->db->queryOne("SELECT baja_token FROM simpatizantes WHERE id = ?", [(int)$simpatizanteId]);
            $token = $r['baja_token'] ?? $token;
        }
        return BASE_URL . '/public/baja.php?t=' . $token;
    }

    /** Texto plano → correo HTML con la imagen de la campaña (se escapa todo; las URL se vuelven enlaces). */
    public static function htmlCorreo($texto, $titulo) {
        $e = htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
        $e = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#b85400">$1</a>', $e);
        return '<div style="font-family:Arial,Helvetica,sans-serif;color:#111;max-width:560px;margin:0 auto">'
            . '<div style="background:#111;color:#fff;padding:16px 22px;border-bottom:4px solid #ff8000;font-size:17px;font-weight:bold">' . htmlspecialchars((string)$titulo, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div style="padding:20px 22px;background:#fff;border:1px solid #e9e4de;border-top:0;font-size:15px;line-height:1.55">' . nl2br($e) . '</div></div>';
    }

    /**
     * Pone en la cola de correos a quienes cumplen el segmento Y aceptaron recibir mensajes.
     * @return array ['encolados' => n, 'omitidos' => n, 'limite' => bool]
     */
    public function enviarCorreoMasivo(array $filtros, $claveBase, $usuarioId, $nombreRemitente) {
        $p = $this->plantilla($claveBase);
        if (!$p || $p['canal'] !== 'correo') { return ['error' => 'Plantilla de correo no válida']; }

        $cfg = $this->db->queryOne("SELECT valor FROM configuracion WHERE clave = 'notificaciones_email'");
        if ($cfg && $cfg['valor'] === 'false') { return ['error' => 'Las notificaciones por correo están desactivadas en Configuración.']; }

        // Se fuerzan las reglas de consentimiento, sin importar lo que pida el filtro
        $filtros['consentimiento'] = 'si';
        $filtros['con_correo'] = 1;
        $total = $this->contar($filtros);
        $filas = $this->segmento($filtros, 1, self::MAX_CORREO_POR_ENVIO);

        $cola = new EmailQueue();
        $contacto = new Contacto();
        $encolados = 0;
        $omitidos = 0;
        foreach ($filas as $f) {
            // Segunda barrera (además del filtro SQL): nunca a quien se dio de baja o no aceptó
            if (!empty($f['baja_comunicacion']) || (int)$f['consentimiento'] !== 1) { $omitidos++; continue; }
            if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) { $omitidos++; continue; }
            $datos = ['enlace_baja' => $this->urlBaja($f['id']), 'capturista' => $nombreRemitente];
            $asunto = self::render($p['asunto'], $f, $datos);
            $cuerpo = self::htmlCorreo(self::render($p['cuerpo'], $f, $datos), $f['campana_nombre'] ?: 'Equipo de campaña');
            if ($cola->queueEmail($f['email'], $asunto, $cuerpo, $f['nombre_completo'], 7, false)) {
                $encolados++;
                $contacto->registrar($f['id'], $usuarioId, 'correo', 'enviado', null, $claveBase);
            } else {
                $omitidos++;
            }
        }
        if ($encolados > 0) { $cola->processQueueAsync(); }
        return ['encolados' => $encolados, 'omitidos' => $omitidos, 'pendientes_por_limite' => max(0, $total - count($filas))];
    }
}
