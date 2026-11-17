<?php
/**
 * Caché de consultas pesadas en archivos (storage/cache), sin dependencias.
 *
 * Cada entrada vive pocos minutos y además se invalida en bloque cuando cambian los datos
 * (alta, edición, borrado o cambio de estado de un simpatizante llaman a invalidar()).
 * Si la carpeta no se puede escribir, simplemente se calcula siempre: nunca rompe nada.
 */
class CacheService {
    const TTL = 120; // segundos

    private static function dir() {
        $d = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/cache';
        if (!is_dir($d)) { @mkdir($d, 0755, true); }
        return is_dir($d) && is_writable($d) ? $d : null;
    }

    /** Versión vigente: cambia cada vez que se invalida. */
    private static function version() {
        $d = self::dir();
        $f = $d ? $d . '/.version' : null;
        return ($f && is_file($f)) ? (string)@file_get_contents($f) : '0';
    }

    /** Marca como vencido todo lo guardado (se llama al modificar simpatizantes). */
    public static function invalidar() {
        $d = self::dir();
        if ($d) { @file_put_contents($d . '/.version', (string)microtime(true), LOCK_EX); }
    }

    /**
     * Devuelve el valor guardado para la clave o lo calcula con $calcular y lo guarda.
     * Solo se guardan resultados serializables (arreglos, números, textos).
     */
    public static function recordar($clave, callable $calcular, $ttl = self::TTL) {
        $d = self::dir();
        if (!$d) { return $calcular(); }

        $archivo = $d . '/' . md5(self::version() . '|' . $clave) . '.cache';
        if (is_file($archivo) && filemtime($archivo) > time() - $ttl) {
            $dato = @unserialize((string)@file_get_contents($archivo), ['allowed_classes' => false]);
            if (is_array($dato) && array_key_exists('v', $dato)) { return $dato['v']; }
        }

        $valor = $calcular();
        if ($valor !== false && $valor !== null) {
            @file_put_contents($archivo, serialize(['v' => $valor]), LOCK_EX);
            if (mt_rand(1, 50) === 1) { self::limpiar($d, $ttl); }
        }
        return $valor;
    }

    /** Borra entradas ya vencidas. */
    private static function limpiar($d, $ttl) {
        foreach (glob($d . '/*.cache') ?: [] as $f) {
            if (filemtime($f) < time() - max($ttl, 600)) { @unlink($f); }
        }
    }
}
