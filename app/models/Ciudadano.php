<?php
/**
 * Modelo Ciudadano: cuentas de la ciudadanía en la app móvil.
 * Son personas distintas de los usuarios del equipo: viven en otra tabla y no tienen ningún permiso del sistema.
 */

class Ciudadano {
    const MAX_INTENTOS = 5;
    const BLOQUEO_MINUTOS = 15;
    const MAX_ALTAS_POR_IP_HORA = 5;

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * @return array ['success' => true, 'id' => n] o ['error' => texto]
     */
    public function registrar(array $d, $ip = null) {
        $nombre = trim(preg_replace('/\s+/u', ' ', (string)($d['nombre'] ?? '')));
        $correo = strtolower(trim((string)($d['correo'] ?? '')));
        $password = (string)($d['password'] ?? '');

        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 120) { return ['error' => 'Escriba su nombre.']; }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150) { return ['error' => 'Escriba un correo válido.']; }
        if (strlen($password) < 8) { return ['error' => 'La contraseña debe tener al menos 8 caracteres.']; }
        if (strlen($password) > 200) { return ['error' => 'La contraseña es demasiado larga.']; }
        if (empty($d['acepto'])) { return ['error' => 'Debe aceptar el aviso de privacidad para crear su cuenta.']; }

        if ($ip) {
            $r = $this->db->queryOne("SELECT COUNT(*) AS n FROM ciudadanos WHERE ip_registro = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)", [$ip]);
            if ((int)($r['n'] ?? 0) >= self::MAX_ALTAS_POR_IP_HORA) {
                return ['error' => 'Se crearon demasiadas cuentas desde esta conexión. Intente más tarde.'];
            }
        }
        if ($this->db->queryOne("SELECT id FROM ciudadanos WHERE correo = ?", [$correo])) {
            return ['error' => 'Ese correo ya tiene una cuenta. Inicie sesión.'];
        }

        $ok = $this->db->execute(
            "INSERT INTO ciudadanos (nombre, correo, password_hash, municipio, colonia, acepto_privacidad_at, ip_registro)
             VALUES (?, ?, ?, ?, ?, NOW(), ?)",
            [$nombre, $correo, password_hash($password, PASSWORD_DEFAULT),
             $this->texto($d['municipio'] ?? '', 100), $this->texto($d['colonia'] ?? '', 100), $ip]
        );
        return $ok ? ['success' => true, 'id' => (int)$this->db->lastInsertId()] : ['error' => 'No se pudo crear la cuenta. Intente de nuevo.'];
    }

    private function texto($v, $max) {
        $v = trim(preg_replace('/\s+/u', ' ', (string)$v));
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    /**
     * @return array|false|array fila de la cuenta, false si las credenciales no coinciden, o ['error' => texto] si está bloqueada
     */
    public function autenticar($correo, $password) {
        $c = $this->db->queryOne("SELECT * FROM ciudadanos WHERE correo = ? AND activo = 1", [strtolower(trim((string)$correo))]);
        if (!$c) { return false; }
        if (!empty($c['bloqueado_hasta']) && strtotime($c['bloqueado_hasta']) > time()) {
            return ['error' => 'Cuenta bloqueada temporalmente por intentos fallidos'];
        }
        if (password_verify((string)$password, $c['password_hash'])) {
            $this->db->execute("UPDATE ciudadanos SET intentos_fallidos = 0, bloqueado_hasta = NULL, last_login = NOW() WHERE id = ?", [(int)$c['id']]);
            return $c;
        }
        $intentos = (int)$c['intentos_fallidos'] + 1;
        if ($intentos >= self::MAX_INTENTOS) {
            $this->db->execute("UPDATE ciudadanos SET intentos_fallidos = 0, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL " . (int)self::BLOQUEO_MINUTOS . " MINUTE) WHERE id = ?", [(int)$c['id']]);
        } else {
            $this->db->execute("UPDATE ciudadanos SET intentos_fallidos = ? WHERE id = ?", [$intentos, (int)$c['id']]);
        }
        return false;
    }

    public function obtener($id) {
        return $this->db->queryOne("SELECT id, nombre, correo, municipio, colonia, quiere_sumarse, created_at FROM ciudadanos WHERE id = ?", [(int)$id]) ?: null;
    }

    public function actualizarZona($id, $municipio, $colonia) {
        return $this->db->execute("UPDATE ciudadanos SET municipio = ?, colonia = ? WHERE id = ?", [$this->texto($municipio, 100), $this->texto($colonia, 100), (int)$id]);
    }

    /** La persona pide sumarse como simpatizante: el equipo lo ve en su panel y la contacta. */
    public function solicitarSumarse($id) {
        return $this->db->execute("UPDATE ciudadanos SET quiere_sumarse = 1, sumarse_at = NOW() WHERE id = ? AND quiere_sumarse = 0", [(int)$id]);
    }

    /**
     * Borra la cuenta y los datos personales. Los reportes ya enviados se conservan, pero desvinculados
     * de la persona (quedan como anónimos), porque pueden ser asuntos públicos aún en atención.
     */
    public function eliminar($id) {
        $id = (int)$id;
        $this->db->beginTransaction();
        $ok = $this->db->execute("UPDATE reportes_ciudadanos SET ciudadano_id = NULL, anonimo = 1 WHERE ciudadano_id = ?", [$id])
           && $this->db->execute("DELETE FROM evento_asistencia WHERE ciudadano_id = ?", [$id])
           && $this->db->execute("DELETE FROM ciudadano_sesiones WHERE ciudadano_id = ?", [$id])
           && $this->db->execute("DELETE FROM ciudadanos WHERE id = ?", [$id]);
        if (!$ok) { $this->db->rollback(); return false; }
        $this->db->commit();
        return true;
    }

    /** Solicitudes pendientes de personas que quieren sumarse (para el panel del equipo). */
    public function quierenSumarse($limite = 50) {
        return $this->db->query("SELECT id, nombre, correo, municipio, colonia, sumarse_at FROM ciudadanos WHERE quiere_sumarse = 1 ORDER BY sumarse_at DESC LIMIT " . (int)$limite) ?: [];
    }
}
