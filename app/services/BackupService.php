<?php
/**
 * Respaldos del sistema
 *
 *  - Base de datos: volcado SQL comprimido (db_AAAAMMDD_HHMMSS.sql.gz), generado con PDO,
 *    por lo que no depende de que el servidor tenga mysqldump.
 *  - Archivos subidos (INE y firmas): uploads_AAAAMMDD_HHMMSS.zip (requiere la extensión zip).
 *
 * Se guardan en storage/backups/, carpeta bloqueada al acceso web.
 * Se conservan los últimos CONSERVAR respaldos de cada tipo.
 */
class BackupService {
    const CONSERVAR = 14;

    public static function directorio() {
        $dir = BASE_PATH . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $ht = BASE_PATH . '/storage/.htaccess';
        if (is_dir(BASE_PATH . '/storage') && !is_file($ht)) {
            @file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new RuntimeException('La carpeta de respaldos no existe o no tiene permisos de escritura: ' . $dir);
        }
        return $dir;
    }

    /**
     * Crea un respaldo completo. Devuelve ['base_datos' => [...], 'archivos' => [...]|null, 'avisos' => [...]]
     */
    public static function crear() {
        @set_time_limit(300);
        $dir = self::directorio();
        $sello = date('Ymd_His');
        $resultado = ['base_datos' => null, 'archivos' => null, 'avisos' => []];

        $resultado['base_datos'] = self::respaldarBaseDatos($dir . '/db_' . $sello . '.sql.gz');

        if (class_exists('ZipArchive')) {
            $resultado['archivos'] = self::respaldarArchivos($dir . '/uploads_' . $sello . '.zip');
            if ($resultado['archivos'] === null) {
                $resultado['avisos'][] = 'No hay archivos subidos que respaldar.';
            }
        } else {
            $resultado['avisos'][] = 'La extensión zip de PHP no está disponible: no se respaldaron los archivos subidos.';
        }

        self::limpiar($dir);
        return $resultado;
    }

    private static function respaldarBaseDatos($ruta) {
        $pdo = Database::getInstance()->getConnection();
        $gz = @gzopen($ruta, 'wb9');
        if (!$gz) {
            throw new RuntimeException('No se pudo crear el archivo de respaldo de la base de datos.');
        }

        gzwrite($gz, "-- Respaldo de " . DB_NAME . " generado el " . date('Y-m-d H:i:s') . "\n");
        gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tablas = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        foreach ($tablas as $t) {
            $q = '`' . str_replace('`', '``', $t[0]) . '`';
            $crear = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM);

            gzwrite($gz, "-- Tabla $q\nDROP TABLE IF EXISTS $q;\n" . $crear[1] . ";\n\n");

            $stmt = $pdo->query("SELECT * FROM $q");
            $columnas = null;
            $lote = [];
            while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($columnas === null) {
                    $columnas = '(`' . implode('`,`', array_map(function ($c) { return str_replace('`', '``', $c); }, array_keys($fila))) . '`)';
                }
                $valores = [];
                foreach ($fila as $v) {
                    $valores[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v);
                }
                $lote[] = '(' . implode(',', $valores) . ')';
                if (count($lote) >= 200) {
                    gzwrite($gz, "INSERT INTO $q $columnas VALUES\n" . implode(",\n", $lote) . ";\n");
                    $lote = [];
                }
            }
            if ($lote) {
                gzwrite($gz, "INSERT INTO $q $columnas VALUES\n" . implode(",\n", $lote) . ";\n");
            }
            gzwrite($gz, "\n");
            $stmt = null;
        }

        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);

        return ['archivo' => basename($ruta), 'tam' => filesize($ruta), 'tablas' => count($tablas)];
    }

    private static function respaldarArchivos($ruta) {
        $base = realpath(UPLOAD_PATH);
        if ($base === false) {
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo zip de respaldo.');
        }

        $total = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $archivo) {
            if (!$archivo->isFile() || in_array($archivo->getFilename(), ['.gitkeep', '.htaccess'], true)) {
                continue;
            }
            $relativa = 'uploads/' . str_replace('\\', '/', substr($archivo->getPathname(), strlen($base) + 1));
            $zip->addFile($archivo->getPathname(), $relativa);
            $total++;
        }
        $zip->close();

        if ($total === 0) {
            @unlink($ruta);
            return null;
        }
        return ['archivo' => basename($ruta), 'tam' => filesize($ruta), 'archivos' => $total];
    }

    /**
     * Lista los respaldos existentes, del más reciente al más antiguo.
     */
    public static function listar($limite = 8) {
        $dir = BASE_PATH . '/storage/backups';
        $lista = [];
        foreach ((array)glob($dir . '/{db_*.sql.gz,uploads_*.zip}', GLOB_BRACE) as $f) {
            $lista[] = [
                'archivo' => basename($f),
                'tipo' => strpos(basename($f), 'db_') === 0 ? 'Base de datos' : 'Archivos subidos',
                'tam' => filesize($f),
                'fecha' => filemtime($f),
            ];
        }
        usort($lista, function ($a, $b) { return $b['fecha'] <=> $a['fecha']; });
        return array_slice($lista, 0, $limite);
    }

    private static function limpiar($dir) {
        foreach (['db_*.sql.gz', 'uploads_*.zip'] as $patron) {
            $archivos = (array)glob($dir . '/' . $patron);
            rsort($archivos); // el sello de fecha en el nombre ordena cronológicamente
            foreach (array_slice($archivos, self::CONSERVAR) as $viejo) {
                @unlink($viejo);
            }
        }
    }

    public static function formatoTamano($bytes) {
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 0) . ' KB';
        return $bytes . ' B';
    }
}
