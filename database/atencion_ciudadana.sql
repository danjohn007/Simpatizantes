-- =====================================================================================
-- ATENCIÓN CIUDADANA  ·  Sistema de Validación de Simpatizantes  ·  MySQL / MariaDB
--
-- Qué hace (todo con CREATE TABLE IF NOT EXISTS; no toca datos existentes):
--   1. `ciudadanos` y `ciudadano_sesiones`: cuentas de la ciudadanía en la app móvil (separadas de los usuarios del equipo).
--   2. `reportes_ciudadanos`, `reporte_archivos`, `reporte_historial`: reportes y denuncias con fotos, folio y seguimiento.
--   3. `eventos`, `evento_asistencia` y `avisos`: eventos y avisos por zona para la ciudadanía.
--
-- Es SEGURO ejecutarlo varias veces. Sin procedimientos ni DELIMITER.
-- Requisito: haber ejecutado antes migracion_servidor.sql, los de Sprint 2 al 5 y app_movil.sql.
-- ANTES: respaldo (phpMyAdmin > Exportar). Luego: phpMyAdmin > su base > SQL > pegar > Continuar.
-- =====================================================================================

SET NAMES utf8mb4;

SELECT DATABASE() AS base_de_datos, VERSION() AS version_mysql;

-- 1. Cuentas de ciudadanos ------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ciudadanos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    correo VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) NULL,
    municipio VARCHAR(100) NULL,
    colonia VARCHAR(100) NULL,
    acepto_privacidad_at DATETIME NULL,
    quiere_sumarse TINYINT(1) NOT NULL DEFAULT 0,   -- pidió sumarse como simpatizante (seguimiento por el equipo)
    sumarse_at DATETIME NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    intentos_fallidos INT NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    ip_registro VARCHAR(45) NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_correo (correo),
    INDEX idx_colonia (municipio, colonia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ciudadano_sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ciudadano_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    dispositivo VARCHAR(120) NULL,
    plataforma VARCHAR(20) NULL,
    app_version VARCHAR(20) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    UNIQUE KEY uq_token_hash (token_hash),
    INDEX idx_ciudadano (ciudadano_id, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Reportes y denuncias --------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS reportes_ciudadanos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folio VARCHAR(20) NULL,
    ciudadano_id INT NULL,                           -- NULL en las denuncias anónimas
    tipo ENUM('reporte','denuncia') NOT NULL DEFAULT 'reporte',
    categoria VARCHAR(30) NOT NULL,                  -- baches, alumbrado, agua, basura, seguridad, otro, denuncia
    descripcion TEXT NOT NULL,
    estado ENUM('recibido','revision','atendido','cerrado') NOT NULL DEFAULT 'recibido',
    anonimo TINYINT(1) NOT NULL DEFAULT 0,
    codigo_hash CHAR(64) NULL,                       -- hash del código con el que una persona anónima consulta su folio
    latitud DECIMAL(10,8) NULL,
    longitud DECIMAL(11,8) NULL,
    municipio VARCHAR(100) NULL,
    colonia VARCHAR(100) NULL,
    asignado_a INT NULL,                             -- usuario del equipo que lo atiende
    ip_address VARCHAR(45) NULL,
    atendido_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_folio (folio),
    INDEX idx_estado (estado, created_at),
    INDEX idx_ciudadano (ciudadano_id, created_at),
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reporte_archivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    mime VARCHAR(50) NOT NULL,
    tamano INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reporte (reporte_id),
    INDEX idx_ruta (ruta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reporte_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    estado_anterior VARCHAR(20) NULL,
    estado_nuevo VARCHAR(20) NOT NULL,
    mensaje VARCHAR(500) NULL,                       -- respuesta que ve la ciudadanía
    usuario_id INT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reporte (reporte_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Eventos y avisos --------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    lugar VARCHAR(200) NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NULL,
    municipio VARCHAR(100) NULL,                     -- NULL = para todos
    colonia VARCHAR(100) NULL,
    publicado TINYINT(1) NOT NULL DEFAULT 1,
    creado_por INT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha (publicado, fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evento_asistencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    ciudadano_id INT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_evento_ciudadano (evento_id, ciudadano_id),
    INDEX idx_ciudadano (ciudadano_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS avisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    texto VARCHAR(500) NOT NULL,
    municipio VARCHAR(100) NULL,
    colonia VARCHAR(100) NULL,
    publicado TINYINT(1) NOT NULL DEFAULT 1,
    vence_at DATETIME NULL,
    creado_por INT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_publicado (publicado, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Reporte final ------------------------------------------------------------------------

SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN
        ('ciudadanos','ciudadano_sesiones','reportes_ciudadanos','reporte_archivos','reporte_historial','eventos','evento_asistencia','avisos')) AS tablas_de_8,
    CASE WHEN
        (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN
            ('ciudadanos','ciudadano_sesiones','reportes_ciudadanos','reporte_archivos','reporte_historial','eventos','evento_asistencia','avisos')) = 8
    THEN 'ATENCION CIUDADANA LISTA' ELSE 'REVISAR: falta algo, ejecute el script de nuevo' END AS resultado;
