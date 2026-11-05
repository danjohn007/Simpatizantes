-- =====================================================================================
-- SPRINT 3 · OPERACIÓN EN CAMPO  ·  Sistema de Validación de Simpatizantes  ·  MySQL / MariaDB
--
-- Qué hace:
--   1. `simpatizantes.uuid_cliente`: identificador que genera el celular al capturar sin conexión;
--      evita registros dobles si la sincronización se reintenta.
--   2. `metas_captura`: metas de captura por campaña, por capturista o por sección, con fechas.
--   3. `asignaciones_seccion`: secciones electorales asignadas a cada capturista.
--
-- Es SEGURO ejecutarlo varias veces. No borra ni modifica datos. Sin procedimientos ni DELIMITER.
-- Requisito: haber ejecutado antes migracion_servidor.sql y sprint2_calidad_datos.sql.
-- ANTES: respaldo (phpMyAdmin > Exportar). Luego: phpMyAdmin > su base > SQL > pegar > Continuar.
-- =====================================================================================

SET NAMES utf8mb4;

SELECT DATABASE() AS base_de_datos, VERSION() AS version_mysql;

-- 1. Captura sin conexión ---------------------------------------------------------------

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'uuid_cliente'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `uuid_cliente` CHAR(36) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND INDEX_NAME = 'uq_uuid_cliente'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD UNIQUE INDEX `uq_uuid_cliente` (`uuid_cliente`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. Metas de captura -------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS metas_captura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campana_id INT NOT NULL,
    usuario_id INT NULL,                -- meta de un capturista (NULL = no es de una persona)
    seccion_electoral VARCHAR(10) NULL, -- meta de una sección (NULL = no es de una sección)
    objetivo INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    creada_por INT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campana (campana_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_fechas (fecha_inicio, fecha_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Zonas asignadas a capturistas -------------------------------------------------------

CREATE TABLE IF NOT EXISTS asignaciones_seccion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    seccion_electoral VARCHAR(10) NOT NULL,
    asignada_por INT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_seccion (usuario_id, seccion_electoral),
    INDEX idx_seccion (seccion_electoral)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Reporte final ------------------------------------------------------------------------

SELECT
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'uuid_cliente') AS uuid_cliente_de_1,
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('metas_captura','asignaciones_seccion')) AS tablas_de_2,
    CASE WHEN
        (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'uuid_cliente') = 1
        AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('metas_captura','asignaciones_seccion')) = 2
    THEN 'SPRINT 3 LISTO' ELSE 'REVISAR: falta algo, ejecute el script de nuevo' END AS resultado;
