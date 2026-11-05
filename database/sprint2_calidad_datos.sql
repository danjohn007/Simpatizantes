-- =====================================================================================
-- SPRINT 2 · CALIDAD DE DATOS  ·  Sistema de Validación de Simpatizantes  ·  MySQL / MariaDB
--
-- Qué hace:
--   1. Agrega a `simpatizantes` el estado de validación (pendiente / validado / rechazado)
--      y el motivo de rechazo. La columna `validado` se conserva y se mantiene sincronizada.
--   2. Crea `simpatizantes_validaciones`: historial de cada cambio de estado (quién, cuándo, por qué).
--   3. Agrega índices para detectar duplicados rápido (WhatsApp, correo, teléfono, nombre + sección).
--   4. Convierte los registros ya validados al nuevo estado.
--
-- Es SEGURO ejecutarlo varias veces. No borra ni modifica datos existentes (solo completa el
-- nuevo estado a partir de `validado`). No usa procedimientos almacenados ni DELIMITER.
--
-- Requisito: haber ejecutado antes migracion_servidor.sql.
-- ANTES DE EJECUTARLO: respaldo (phpMyAdmin > Exportar). Luego: phpMyAdmin > su base > SQL > pegar > Continuar.
-- =====================================================================================

SET NAMES utf8mb4;

SELECT DATABASE() AS base_de_datos, VERSION() AS version_mysql;

-- 1. Estado de validación -------------------------------------------------------------

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'telefono'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `telefono` VARCHAR(20) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'observaciones'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `observaciones` TEXT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'estatus_validacion'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `estatus_validacion` ENUM(''pendiente'',''validado'',''rechazado'') NOT NULL DEFAULT ''pendiente''');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'motivo_rechazo'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `motivo_rechazo` VARCHAR(255) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Los registros que ya estaban validados pasan al nuevo estado (solo los que siguen en 'pendiente')
UPDATE simpatizantes SET estatus_validacion = 'validado' WHERE validado = 1 AND estatus_validacion = 'pendiente';

-- 2. Historial de validación -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS simpatizantes_validaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    simpatizante_id INT NOT NULL,
    estatus_anterior ENUM('pendiente','validado','rechazado') NULL,
    estatus_nuevo ENUM('pendiente','validado','rechazado') NOT NULL,
    motivo VARCHAR(255) NULL,
    usuario_id INT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_simpatizante (simpatizante_id, created_at),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Índices para la detección de duplicados ----------------------------------------------

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_clave_elector' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'clave_elector'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_clave_elector` (`clave_elector`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_curp' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'curp'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_curp` (`curp`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_whatsapp' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'whatsapp'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_whatsapp` (`whatsapp`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_email' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'email'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_email` (`email`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_telefono' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'telefono'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_telefono` (`telefono`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_estatus' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'estatus_validacion'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_estatus` (`estatus_validacion`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_seccion_nombre' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = '__ninguna__'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_seccion_nombre` (`seccion_electoral`, `nombre_completo`(60))');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- 4. Reporte final -------------------------------------------------------------------------

SELECT
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME IN ('estatus_validacion','motivo_rechazo','telefono')) AS columnas_nuevas_de_3,
    (SELECT COUNT(*) FROM information_schema.TABLES  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes_validaciones') AS historial_de_validacion_de_1,
    (SELECT COUNT(*) FROM simpatizantes WHERE estatus_validacion = 'validado') AS validados,
    (SELECT COUNT(*) FROM simpatizantes WHERE estatus_validacion = 'pendiente') AS pendientes,
    (SELECT COUNT(*) FROM simpatizantes WHERE estatus_validacion = 'rechazado') AS rechazados,
    CASE WHEN
        (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME IN ('estatus_validacion','motivo_rechazo','telefono')) = 3
        AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes_validaciones') = 1
    THEN 'SPRINT 2 LISTO' ELSE 'REVISAR: falta algo, ejecute el script de nuevo' END AS resultado;
