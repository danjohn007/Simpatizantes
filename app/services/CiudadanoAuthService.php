<?php
/**
 * Sesiones de la app móvil para ciudadanos (token opaco, igual que AppAuthService pero en otra tabla,
 * para que una cuenta ciudadana nunca pueda confundirse con un usuario del equipo).
 */
class CiudadanoAuthService {
    const VIDA_DIAS = 60;

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function disponible() {
        static $ok = null;
        if ($ok === null) {
            $r = $this->db->queryOne("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ciudadanos', 'ciudadano_sesiones', 'reportes_ciudadanos', 'eventos')");
            $ok = isset($r['n']) && (int)$r['n'] === 4;
        }
        return $ok;
    }

    private static function hash($token) {
        return hash('sha256', $token);
    }

    public function emitir($ciudadanoId, $dispositivo, $plataforma, $appVersion) {
        $token = bin2hex(random_bytes(32));
        $ok = $this->db->execute(
            "INSERT INTO ciudadano_sesiones (ciudadano_id, token_hash, dispositivo, plataforma, app_version, ip_address, last_used_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL " . (int)self::VIDA_DIAS . " DAY))",
            [(int)$ciudadanoId, self::hash($token), mb_substr((string)$dispositivo, 0, 120), mb_substr((string)$plataforma, 0, 20),
             mb_substr((string)$appVersion, 0, 20), $_SERVER['REMOTE_ADDR'] ?? null]
        );
        return $ok ? $token : null;
    }

    /** Valida el token y devuelve a la persona (solo si la sesión sigue vigente y la cuenta activa). */
    public function validar($token) {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) { return null; }
        $r = $this->db->queryOne(
            "SELECT s.id AS sesion_id, s.last_used_at, c.id, c.nombre, c.correo, c.municipio, c.colonia, c.quiere_sumarse
             FROM ciudadano_sesiones s INNER JOIN ciudadanos c ON c.id = s.ciudadano_id
             WHERE s.token_hash = ? AND s.revoked_at IS NULL AND s.expires_at > NOW() AND c.activo = 1",
            [self::hash($token)]
        );
        if (!$r) { return null; }
        if (!$r['last_used_at'] || strtotime($r['last_used_at']) < time() - 3600) {
            $this->db->execute(
                "UPDATE ciudadano_sesiones SET last_used_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL " . (int)self::VIDA_DIAS . " DAY) WHERE id = ?",
                [(int)$r['sesion_id']]
            );
        }
        return $r;
    }

    public function revocar($token) {
        return $this->db->execute("UPDATE ciudadano_sesiones SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL", [self::hash((string)$token)]);
    }
}
