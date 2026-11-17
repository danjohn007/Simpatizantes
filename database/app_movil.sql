-- =====================================================================================
-- APP MÓVIL · SESIONES Y TEMAS DE COLONIA  ·  Sistema de Validación de Simpatizantes  ·  MySQL / MariaDB
--
-- Qué hace:
--   1. `app_sesiones`: sesiones de la app móvil (token guardado como hash, por dispositivo, revocable).
--   2. `simpatizante_temas`: qué le gustaría mejorar a cada persona en su colonia (agua, seguridad, baches…).
--   3. En `simpatizantes`: colonia y un comentario libre sobre su colonia.
--   4. Parámetro `app_version_minima`: versión mínima de la app que puede sincronizar.
--
-- Es SEGURO ejecutarlo varias veces. No borra ni modifica datos. Sin procedimientos ni DELIMITER.
-- Requisito: haber ejecutado antes migracion_servidor.sql y los de Sprint 2 al 5.
-- ANTES: respaldo (phpMyAdmin > Exportar). Luego: phpMyAdmin > su base > SQL > pegar > Continuar.
-- =====================================================================================

SET NAMES utf8mb4;

SELECT DATABASE() AS base_de_datos, VERSION() AS version_mysql;

-- 1. Sesiones de la app ------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS app_sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,          -- SHA-256 del token; el token en claro solo lo tiene el teléfono
    dispositivo VARCHAR(120) NULL,
    plataforma VARCHAR(20) NULL,
    app_version VARCHAR(20) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    UNIQUE KEY uq_token_hash (token_hash),
    INDEX idx_usuario (usuario_id, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Temas de colonia ----------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS simpatizante_temas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    simpatizante_id INT NOT NULL,
    tema VARCHAR(30) NOT NULL,             -- agua, drenaje, seguridad, alumbrado, baches, basura, transporte, salud, educacion, empleo, parques, otro
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_simp_tema (simpatizante_id, tema),
    INDEX idx_tema (tema)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'colonia'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `colonia` VARCHAR(100) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'comentario_colonia'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `comentario_colonia` VARCHAR(500) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- 3. Versión mínima de la app --------------------------------------------------------------

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'app_version_minima', '1.0.0', 'Versión mínima de la app móvil que puede sincronizar', 'texto'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'app_version_minima');

-- 4. Reporte final ------------------------------------------------------------------------

SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('app_sesiones','simpatizante_temas')) AS tablas_de_2,
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME IN ('colonia','comentario_colonia')) AS columnas_de_2,
    CASE WHEN
        (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('app_sesiones','simpatizante_temas')) = 2
        AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME IN ('colonia','comentario_colonia')) = 2
    THEN 'APP MOVIL LISTA' ELSE 'REVISAR: falta algo, ejecute el script de nuevo' END AS resultado;
