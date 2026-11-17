-- =====================================================================================
-- SPRINT 4 · ANÁLISIS Y RENDIMIENTO  ·  Sistema de Validación de Simpatizantes  ·  MySQL / MariaDB
--
-- Qué hace:
--   1. Índices compuestos para que el tablero, los reportes, el mapa y las exportaciones sigan
--      siendo rápidos con decenas de miles de simpatizantes (filtros por campaña / capturista /
--      ciudad + rango de fechas).
--   2. Índices para la auditoría (por fecha y acción).
--   3. Parámetros del reporte semanal por correo (apagado por defecto).
--
-- Es SEGURO ejecutarlo varias veces. No borra ni modifica datos. Sin procedimientos ni DELIMITER.
-- Requisito: haber ejecutado antes migracion_servidor.sql, sprint2_calidad_datos.sql y sprint3_campo.sql.
-- ANTES: respaldo (phpMyAdmin > Exportar). Luego: phpMyAdmin > su base > SQL > pegar > Continuar.
-- En tablas muy grandes, crear un índice puede tardar unos segundos.
-- =====================================================================================

SET NAMES utf8mb4;

SELECT DATABASE() AS base_de_datos, VERSION() AS version_mysql;

-- (El índice por estado solo se crea si ya se ejecutó sprint2_calidad_datos.sql; si no, se omite
--  y el reporte final lo avisa. Después de ejecutar el de Sprint 2, vuelva a correr este.)

-- 1. Índices de simpatizantes ------------------------------------------------------------

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND INDEX_NAME = 'idx_campana_fecha'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_campana_fecha` (`campana_id`, `created_at`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND INDEX_NAME = 'idx_capturista_fecha'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_capturista_fecha` (`capturista_id`, `created_at`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND INDEX_NAME = 'idx_ciudad'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_ciudad` (`ciudad`(50))');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND INDEX_NAME = 'idx_estatus_fecha') OR NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'estatus_validacion'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_estatus_fecha` (`estatus_validacion`, `created_at`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. Índices de auditoría y usuarios ------------------------------------------------------

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND INDEX_NAME = 'idx_log_fecha'), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD INDEX `idx_log_fecha` (`created_at`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND INDEX_NAME = 'idx_log_accion'), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD INDEX `idx_log_accion` (`accion`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'idx_rol_campana'), 'DO 0', 'ALTER TABLE `usuarios` ADD INDEX `idx_rol_campana` (`rol`, `campana_id`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- 3. Reporte semanal por correo --------------------------------------------------------------

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'reporte_semanal_activo', 'false', 'Enviar cada semana el reporte en PDF a coordinadores, candidatos y administradores', 'boolean'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'reporte_semanal_activo');

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'reporte_semanal_ultimo', '', 'Fecha del último reporte semanal enviado', 'texto'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'reporte_semanal_ultimo');

-- 4. Reporte final ------------------------------------------------------------------------

SELECT
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes'
        AND INDEX_NAME IN ('idx_campana_fecha','idx_capturista_fecha','idx_ciudad','idx_estatus_fecha') AND SEQ_IN_INDEX = 1) AS indices_de_4,
    (SELECT COUNT(*) FROM configuracion WHERE clave IN ('reporte_semanal_activo','reporte_semanal_ultimo')) AS parametros_de_2,
    CASE WHEN
        (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes'
            AND INDEX_NAME IN ('idx_campana_fecha','idx_capturista_fecha','idx_ciudad','idx_estatus_fecha') AND SEQ_IN_INDEX = 1) = 4
        AND (SELECT COUNT(*) FROM configuracion WHERE clave IN ('reporte_semanal_activo','reporte_semanal_ultimo')) = 2
    THEN 'SPRINT 4 LISTO' ELSE 'REVISAR: falta ejecutar antes sprint2_calidad_datos.sql; hágalo y vuelva a correr este script' END AS resultado;
