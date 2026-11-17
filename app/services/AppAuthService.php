<?php
/**
 * Sesiones de la app móvil con token opaco.
 *
 * El teléfono recibe un token aleatorio de 64 caracteres; en la base solo se guarda su hash SHA-256.
 * Cada token vive 30 días (se renueva al usarse), pertenece a un dispositivo y se puede revocar:
 * al cerrar sesión, al suspender al usuario o desde el panel.
 */
class AppAuthService {
    const VIDA_DIAS = 30;

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function disponible() {
        static $ok = null;
        if ($ok === null) {
            $r = $this->db->queryOne("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_sesiones'");
            $ok = !empty($r['n']);
        }
        return $ok;
    }

    private static function hash($token) {
        return hash('sha256', $token);
    }

    /** Crea una sesión y devuelve el token en claro (única vez que existe fuera del teléfono). */
    public function emitir($usuarioId, $dispositivo, $plataforma, $appVersion) {
        $token = bin2hex(random_bytes(32));
        $ok = $this->db->execute(
            "INSERT INTO app_sesiones (usuario_id, token_hash, dispositivo, plataforma, app_version, ip_address, last_used_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL " . (int)self::VIDA_DIAS . " DAY))",
            [(int)$usuarioId, self::hash($token), mb_substr((string)$dispositivo, 0, 120), mb_substr((string)$plataforma, 0, 20),
             mb_substr((string)$appVersion, 0, 20), $_SERVER['REMOTE_ADDR'] ?? null]
        );
        return $ok ? $token : null;
    }

    /**
     * Valida un token y devuelve el usuario (solo si la sesión sigue vigente y el usuario está activo).
     * @return array|null fila del usuario con 'sesion_id'
     */
    public function validar($token) {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) { return null; }
        $r = $this->db->queryOne(
            "SELECT s.id AS sesion_id, s.last_used_at, u.id, u.username, u.nombre_completo, u.email, u.rol, u.campana_id
             FROM app_sesiones s INNER JOIN usuarios u ON u.id = s.usuario_id
             WHERE s.token_hash = ? AND s.revoked_at IS NULL AND s.expires_at > NOW() AND (u.activo = 1 OR u.activo IS NULL)",
            [self::hash($token)]
        );
        if (!$r) { return null; }
        // Renovación: como máximo una vez por hora, para no escribir en cada petición
        if (!$r['last_used_at'] || strtotime($r['last_used_at']) < time() - 3600) {
            $this->db->execute(
                "UPDATE app_sesiones SET last_used_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL " . (int)self::VIDA_DIAS . " DAY) WHERE id = ?",
                [(int)$r['sesion_id']]
            );
        }
        return $r;
    }

    public function revocar($token) {
        return $this->db->execute("UPDATE app_sesiones SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL", [self::hash((string)$token)]);
    }

    /** Cierra todas las sesiones de un usuario (por ejemplo, al suspenderlo). */
    public function revocarDeUsuario($usuarioId) {
        return $this->db->execute("UPDATE app_sesiones SET revoked_at = NOW() WHERE usuario_id = ? AND revoked_at IS NULL", [(int)$usuarioId]);
    }
}
