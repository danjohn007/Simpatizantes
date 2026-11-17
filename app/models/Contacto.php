<?php
/**
 * Modelo Contacto: bitácora de llamadas, visitas, WhatsApp y correos a simpatizantes.
 */

class Contacto {
    const CANALES = ['llamada' => 'Llamada', 'visita' => 'Visita', 'whatsapp' => 'WhatsApp', 'correo' => 'Correo', 'otro' => 'Otro'];
    const RESULTADOS = [
        'contesto' => 'Contestó', 'no_contesto' => 'No contestó', 'interesado' => 'Interesado',
        'no_interesado' => 'No interesado', 'volver_a_llamar' => 'Volver a llamar',
        'enviado' => 'Mensaje enviado', 'baja' => 'Pidió baja', 'otro' => 'Otro',
    ];

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /** ¿Se ejecutó la migración del Sprint 5? (tabla de contactos y columnas de consentimiento) */
    public function disponible() {
        static $ok = null;
        if ($ok === null) {
            $r = $this->db->queryOne(
                "SELECT (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('contactos_simpatizante', 'plantillas_mensaje'))
                      + (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes'
                         AND COLUMN_NAME IN ('consentimiento', 'baja_comunicacion', 'baja_token', 'ultimo_contacto')) AS n"
            );
            $ok = isset($r['n']) && (int)$r['n'] === 6;
        }
        return $ok;
    }

    /**
     * Anota un contacto y actualiza la fecha de último contacto del simpatizante.
     * @return array ['success' => true, 'id' => n] o ['error' => texto]
     */
    public function registrar($simpatizanteId, $usuarioId, $canal, $resultado, $nota = '', $plantilla = null) {
        if (!isset(self::CANALES[$canal])) { return ['error' => 'Canal de contacto no válido']; }
        if (!isset(self::RESULTADOS[$resultado])) { return ['error' => 'Resultado no válido']; }
        $nota = trim((string)$nota);
        if (mb_strlen($nota) > 500) { return ['error' => 'La nota no puede pasar de 500 caracteres']; }

        $ok = $this->db->execute(
            "INSERT INTO contactos_simpatizante (simpatizante_id, usuario_id, canal, resultado, nota, plantilla) VALUES (?, ?, ?, ?, ?, ?)",
            [(int)$simpatizanteId, $usuarioId ?: null, $canal, $resultado, $nota !== '' ? $nota : null, $plantilla ?: null]
        );
        if (!$ok) {
            return ['error' => $this->db->describirError('registrar el contacto')];
        }
        $id = $this->db->lastInsertId();
        $this->db->execute("UPDATE simpatizantes SET ultimo_contacto = NOW() WHERE id = ?", [(int)$simpatizanteId]);
        return ['success' => true, 'id' => $id];
    }

    /** Historial de contactos de un simpatizante, del más reciente al más antiguo. */
    public function historial($simpatizanteId, $limite = 30) {
        return $this->db->query(
            "SELECT c.*, u.nombre_completo AS usuario_nombre
             FROM contactos_simpatizante c LEFT JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.simpatizante_id = ? ORDER BY c.created_at DESC, c.id DESC LIMIT " . (int)$limite,
            [(int)$simpatizanteId]
        ) ?: [];
    }

    /**
     * Cambia el consentimiento de un simpatizante.
     * $valor: 'si' (acepta), 'no' (no acepta), 'sin_registrar' (se borra la respuesta)
     */
    public function fijarConsentimiento($simpatizanteId, $valor) {
        if ($valor === 'si') {
            return $this->db->execute("UPDATE simpatizantes SET consentimiento = 1, consentimiento_fecha = NOW() WHERE id = ?", [(int)$simpatizanteId]);
        }
        if ($valor === 'no') {
            return $this->db->execute("UPDATE simpatizantes SET consentimiento = 0, consentimiento_fecha = NOW() WHERE id = ?", [(int)$simpatizanteId]);
        }
        return $this->db->execute("UPDATE simpatizantes SET consentimiento = NULL, consentimiento_fecha = NULL WHERE id = ?", [(int)$simpatizanteId]);
    }

    /** Quita la baja de comunicación (solo lo hace un administrador, con constancia). */
    public function revertirBaja($simpatizanteId) {
        return $this->db->execute("UPDATE simpatizantes SET baja_comunicacion = 0 WHERE id = ?", [(int)$simpatizanteId]);
    }

    /** Baja pedida por la propia persona, a partir de su enlace personal. */
    public function darDeBajaPorToken($token) {
        $s = $this->db->queryOne("SELECT id, baja_comunicacion FROM simpatizantes WHERE baja_token = ?", [$token]);
        if (!$s) { return null; }
        if ((int)$s['baja_comunicacion'] !== 1) {
            $this->db->execute("UPDATE simpatizantes SET baja_comunicacion = 1, consentimiento = 0, consentimiento_fecha = NOW() WHERE id = ?", [(int)$s['id']]);
            $this->registrar($s['id'], null, 'correo', 'baja', 'Se dio de baja desde el enlace del correo');
        }
        return (int)$s['id'];
    }
}
