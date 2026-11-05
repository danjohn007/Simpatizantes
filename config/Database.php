<?php
/**
 * Clase Database
 * Maneja la conexión a la base de datos usando PDO
 */

class Database {
    private static $instance = null;
    private $connection;
    private $lastError = null;
    
    /**
     * Constructor privado para patrón Singleton
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Error de conexión a la base de datos: " . $e->getMessage());
            http_response_code(500);
            die("No fue posible conectar con la base de datos. Intente más tarde.");
        }
    }
    
    /**
     * Obtiene la instancia única de la base de datos
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtiene la conexión PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Ejecuta una consulta SELECT
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->registrarError($e, "query");
            return false;
        }
    }
    
    /**
     * Ejecuta una consulta SELECT y retorna una sola fila
     */
    public function queryOne($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            $this->registrarError($e, "queryOne");
            return false;
        }
    }
    
    /**
     * Ejecuta una consulta INSERT, UPDATE o DELETE
     */
    public function execute($sql, $params = []) {
        $this->lastError = null;
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            $this->registrarError($e, "execute");
            return false;
        }
    }
    
    /**
     * Guarda y registra el último error de base de datos
     */
    private function registrarError(PDOException $e, $origen) {
        $this->lastError = [
            'code'    => isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0,
            'message' => $e->getMessage(),
        ];
        error_log("Error en $origen: " . $e->getMessage());
    }

    /**
     * Mensaje comprensible para el usuario según el último error de base de datos.
     * No incluye datos sensibles; el detalle completo queda en el log del servidor.
     */
    public function describirError($contexto) {
        if (!$this->lastError) {
            return "No se pudo $contexto.";
        }
        $code = $this->lastError['code'];
        $msg  = $this->lastError['message'];
        switch ($code) {
            case 1062:
                return "No se pudo $contexto: ya existe un registro con esos datos.";
            case 1452:
                return "No se pudo $contexto: un dato relacionado (por ejemplo la campaña) ya no existe.";
            case 1054:
                preg_match("/Unknown column '([^']+)'/", $msg, $m);
                return "No se pudo $contexto: a la base de datos le falta la columna '" . ($m[1] ?? '?') . "'. Hay que actualizar su estructura.";
            case 1406:
                return "No se pudo $contexto: alguno de los textos es más largo de lo permitido.";
            case 1048:
            case 1364:
                return "No se pudo $contexto: falta un dato obligatorio.";
            case 1146:
                return "No se pudo $contexto: falta una tabla en la base de datos.";
            case 1044:
            case 1142:
                return "No se pudo $contexto: el usuario de la base de datos no tiene permiso para escribir (código $code).";
            default:
                return "No se pudo $contexto (error de base de datos $code). Revise el registro de errores del servidor.";
        }
    }

    /**
     * Obtiene el último ID insertado
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * Inicia una transacción
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Confirma una transacción
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Revierte una transacción
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    /**
     * Prevenir clonación del objeto
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización del objeto
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
