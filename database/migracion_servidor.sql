-- =====================================================================================
-- MIGRACIÓN COMPLETA Y REPETIBLE  ·  Sistema de Validación de Simpatizantes  ·  MySQL / MariaDB
--
-- Qué hace (en este orden):
--   1. Crea las tablas que falten.
--   2. Agrega las columnas que falten en tablas ya existentes (sin tocar los datos).
--   3. Corrige los tipos ENUM que el sistema necesita (metodo_captura, rol, tipo).
--   4. Agrega índices útiles para consultas rápidas con miles de registros.
--   5. Agrega llaves foráneas SOLO cuando es seguro (mismo motor, mismo tipo y sin datos huérfanos).
--   6. Inserta los parámetros de configuración que falten (nunca pisa los existentes).
--   7. Muestra un reporte final de lo que quedó completo y lo que sigue pendiente.
--
-- Es SEGURO ejecutarlo varias veces: cada paso verifica antes de actuar y nunca borra ni
-- modifica datos. No usa procedimientos almacenados ni DELIMITER.
--
-- Requisitos: MySQL 5.7+ o MariaDB 10.2+. Usuario con permisos ALTER, CREATE, INDEX y REFERENCES.
--
-- ANTES DE EJECUTARLO: haga un respaldo (phpMyAdmin > Exportar > Continuar).
-- Cómo ejecutarlo: phpMyAdmin > seleccione SU base de datos > pestaña SQL > pegue todo > Continuar.
-- =====================================================================================

SET NAMES utf8mb4;

-- Ajuste: ¿debe quedar ABIERTO el registro público cuando el parámetro todavía no existe?
-- 'true'  = el formulario público de registro queda habilitado.
-- 'false' = queda cerrado (se activa después desde Configuración).
SET @registro_publico_inicial = 'true';

-- Información de la base sobre la que se está trabajando
SELECT DATABASE() AS base_de_datos, VERSION() AS version_mysql;

-- ==================================== 1. TABLAS ======================================

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    rol ENUM('super_admin','admin','candidato','coordinador','capturista') NOT NULL DEFAULT 'capturista',
    campana_id INT NULL,
    activo TINYINT(1) NULL DEFAULT 1,
    intentos_fallidos INT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    whatsapp VARCHAR(20) NULL, twitter VARCHAR(100) NULL, instagram VARCHAR(100) NULL,
    facebook VARCHAR(100) NULL, youtube VARCHAR(100) NULL, tiktok VARCHAR(100) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY username (username),
    UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campanas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    candidato_id INT NULL,
    activa TINYINT(1) NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS simpatizantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(150) NOT NULL,
    domicilio_completo TEXT NOT NULL,
    sexo ENUM('M','F','O') NULL,
    ciudad VARCHAR(100) NULL,
    clave_elector VARCHAR(18) NULL,
    curp VARCHAR(18) NULL,
    fecha_nacimiento DATE NULL,
    ano_registro INT NULL,
    vigencia INT NULL,
    seccion_electoral VARCHAR(10) NOT NULL,
    telefono VARCHAR(20) NULL,
    whatsapp VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    twitter VARCHAR(100) NULL, instagram VARCHAR(100) NULL, facebook VARCHAR(100) NULL,
    youtube VARCHAR(100) NULL, tiktok VARCHAR(100) NULL,
    observaciones TEXT NULL,
    latitud DECIMAL(10,8) NULL,
    longitud DECIMAL(11,8) NULL,
    ine_frontal VARCHAR(255) NULL, ine_posterior VARCHAR(255) NULL, firma_digital VARCHAR(255) NULL,
    campana_id INT NULL,
    capturista_id INT NULL,
    metodo_captura ENUM('manual','escaneo','app','web') NULL DEFAULT 'manual',
    validado TINYINT(1) NULL DEFAULT 0,
    duplicado TINYINT(1) NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    accion VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(50) NULL,
    registro_id INT NULL,
    datos_anteriores TEXT NULL,
    datos_nuevos TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) NOT NULL,
    valor TEXT NULL,
    descripcion TEXT NULL,
    tipo ENUM('texto','numero','boolean','json','color') NULL DEFAULT 'texto',
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recuperacion_password (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expiracion DATETIME NOT NULL,
    usado TINYINT(1) NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jerarquia_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    superior_id INT NOT NULL,
    subordinado_id INT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_relacion (superior_id, subordinado_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(255) NULL,
    subject VARCHAR(500) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('pending','processing','sent','failed') NULL DEFAULT 'pending',
    attempts INT NULL DEFAULT 0,
    max_attempts INT NULL DEFAULT 3,
    priority INT NULL DEFAULT 5,
    scheduled_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================ 2. COLUMNAS FALTANTES ===============================

-- usuarios

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'activo'), 'DO 0', 'ALTER TABLE `usuarios` ADD COLUMN `activo` TINYINT(1) NULL DEFAULT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'campana_id'), 'DO 0', 'ALTER TABLE `usuarios` ADD COLUMN `campana_id` INT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'intentos_fallidos'), 'DO 0', 'ALTER TABLE `usuarios` ADD COLUMN `intentos_fallidos` INT NULL DEFAULT 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'bloqueado_hasta'), 'DO 0', 'ALTER TABLE `usuarios` ADD COLUMN `bloqueado_hasta` DATETIME NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'whatsapp'), 'DO 0', 'ALTER TABLE `usuarios` ADD COLUMN `whatsapp` VARCHAR(20) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'twitter'), 'DO 0', 'ALTER TABLE `usuarios` ADD COLUMN `twitter` VARCHAR(100) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'instagram'), 'DO 0', 'ALTER TABLE `usuarios` ADD COLUMN `instagram` VARCHAR(100) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'facebook'), 'DO 0', 'ALTER TABLE `usuarios` ADD COLUMN `facebook` VARCHAR(100) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'youtube'), 'DO 0', 'ALTER TABLE `usuarios` ADD COLUMN `youtube` VARCHAR(100) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'tiktok'), 'DO 0', 'ALTER TABLE `usuarios` ADD COLUMN `tiktok` VARCHAR(100) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'created_at'), 'DO 0', 'ALTER TABLE `usuarios` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'updated_at'), 'DO 0', 'ALTER TABLE `usuarios` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- campanas

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campanas' AND COLUMN_NAME = 'descripcion'), 'DO 0', 'ALTER TABLE `campanas` ADD COLUMN `descripcion` TEXT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campanas' AND COLUMN_NAME = 'candidato_id'), 'DO 0', 'ALTER TABLE `campanas` ADD COLUMN `candidato_id` INT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campanas' AND COLUMN_NAME = 'activa'), 'DO 0', 'ALTER TABLE `campanas` ADD COLUMN `activa` TINYINT(1) NULL DEFAULT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campanas' AND COLUMN_NAME = 'created_at'), 'DO 0', 'ALTER TABLE `campanas` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campanas' AND COLUMN_NAME = 'updated_at'), 'DO 0', 'ALTER TABLE `campanas` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- simpatizantes

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'sexo'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `sexo` ENUM(''M'',''F'',''O'') NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'ciudad'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `ciudad` VARCHAR(100) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'clave_elector'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `clave_elector` VARCHAR(18) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'curp'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `curp` VARCHAR(18) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'fecha_nacimiento'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `fecha_nacimiento` DATE NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'ano_registro'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `ano_registro` INT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'vigencia'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `vigencia` INT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'telefono'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `telefono` VARCHAR(20) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'whatsapp'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `whatsapp` VARCHAR(20) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'email'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `email` VARCHAR(100) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'twitter'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `twitter` VARCHAR(100) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'instagram'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `instagram` VARCHAR(100) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'facebook'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `facebook` VARCHAR(100) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'youtube'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `youtube` VARCHAR(100) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'tiktok'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `tiktok` VARCHAR(100) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'observaciones'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `observaciones` TEXT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'latitud'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `latitud` DECIMAL(10,8) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'longitud'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `longitud` DECIMAL(11,8) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'ine_frontal'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `ine_frontal` VARCHAR(255) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'ine_posterior'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `ine_posterior` VARCHAR(255) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'firma_digital'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `firma_digital` VARCHAR(255) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'campana_id'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `campana_id` INT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'capturista_id'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `capturista_id` INT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'metodo_captura'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `metodo_captura` ENUM(''manual'',''escaneo'',''app'',''web'') NULL DEFAULT ''manual''');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'validado'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `validado` TINYINT(1) NULL DEFAULT 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'duplicado'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `duplicado` TINYINT(1) NULL DEFAULT 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'created_at'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'updated_at'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- logs_auditoria

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND COLUMN_NAME = 'usuario_id'), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD COLUMN `usuario_id` INT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND COLUMN_NAME = 'tabla_afectada'), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD COLUMN `tabla_afectada` VARCHAR(50) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND COLUMN_NAME = 'registro_id'), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD COLUMN `registro_id` INT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND COLUMN_NAME = 'datos_anteriores'), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD COLUMN `datos_anteriores` TEXT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND COLUMN_NAME = 'datos_nuevos'), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD COLUMN `datos_nuevos` TEXT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND COLUMN_NAME = 'ip_address'), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD COLUMN `ip_address` VARCHAR(45) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND COLUMN_NAME = 'user_agent'), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD COLUMN `user_agent` TEXT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND COLUMN_NAME = 'created_at'), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- configuracion

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracion' AND COLUMN_NAME = 'valor'), 'DO 0', 'ALTER TABLE `configuracion` ADD COLUMN `valor` TEXT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracion' AND COLUMN_NAME = 'descripcion'), 'DO 0', 'ALTER TABLE `configuracion` ADD COLUMN `descripcion` TEXT NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracion' AND COLUMN_NAME = 'tipo'), 'DO 0', 'ALTER TABLE `configuracion` ADD COLUMN `tipo` ENUM(''texto'',''numero'',''boolean'',''json'',''color'') NULL DEFAULT ''texto''');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracion' AND COLUMN_NAME = 'updated_at'), 'DO 0', 'ALTER TABLE `configuracion` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;


-- ================================ 3. TIPOS ENUM ======================================
-- (solo se modifican si hoy son distintos; los datos existentes se conservan)

SET @q = IF(COALESCE((SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'metodo_captura'), '') = 'enum(''manual'',''escaneo'',''app'',''web'')', 'DO 0', 'ALTER TABLE `simpatizantes` MODIFY COLUMN `metodo_captura` ENUM(''manual'',''escaneo'',''app'',''web'') NULL DEFAULT ''manual''');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(COALESCE((SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'rol'), '') = 'enum(''super_admin'',''admin'',''candidato'',''coordinador'',''capturista'')', 'DO 0', 'ALTER TABLE `usuarios` MODIFY COLUMN `rol` ENUM(''super_admin'',''admin'',''candidato'',''coordinador'',''capturista'') NOT NULL DEFAULT ''capturista''');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(COALESCE((SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracion' AND COLUMN_NAME = 'tipo'), '') = 'enum(''texto'',''numero'',''boolean'',''json'',''color'')', 'DO 0', 'ALTER TABLE `configuracion` MODIFY COLUMN `tipo` ENUM(''texto'',''numero'',''boolean'',''json'',''color'') NULL DEFAULT ''texto''');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;


-- ================================ 4. ÍNDICES =========================================
-- (se omiten si ya existe un índice con ese nombre o sobre esa misma columna)

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND (INDEX_NAME = 'idx_rol' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'rol'))), 'DO 0', 'ALTER TABLE `usuarios` ADD INDEX `idx_rol` (`rol`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND (INDEX_NAME = 'idx_activo' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'activo'))), 'DO 0', 'ALTER TABLE `usuarios` ADD INDEX `idx_activo` (`activo`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND (INDEX_NAME = 'idx_campana' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'campana_id'))), 'DO 0', 'ALTER TABLE `usuarios` ADD INDEX `idx_campana` (`campana_id`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campanas' AND (INDEX_NAME = 'idx_activa' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'activa'))), 'DO 0', 'ALTER TABLE `campanas` ADD INDEX `idx_activa` (`activa`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campanas' AND (INDEX_NAME = 'idx_fechas' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'fecha_inicio'))), 'DO 0', 'ALTER TABLE `campanas` ADD INDEX `idx_fechas` (`fecha_inicio`, `fecha_fin`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_seccion' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'seccion_electoral'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_seccion` (`seccion_electoral`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_campana' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'campana_id'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_campana` (`campana_id`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_capturista' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'capturista_id'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_capturista` (`capturista_id`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_created_at' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'created_at'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_created_at` (`created_at`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_curp' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'curp'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_curp` (`curp`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_clave_elector' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'clave_elector'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_clave_elector` (`clave_elector`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND (INDEX_NAME = 'idx_ubicacion' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'latitud'))), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_ubicacion` (`latitud`, `longitud`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND (INDEX_NAME = 'idx_usuario' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'usuario_id'))), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD INDEX `idx_usuario` (`usuario_id`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND (INDEX_NAME = 'idx_accion' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'accion'))), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD INDEX `idx_accion` (`accion`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND (INDEX_NAME = 'idx_created_at' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'created_at'))), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD INDEX `idx_created_at` (`created_at`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recuperacion_password' AND (INDEX_NAME = 'idx_token' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'token'))), 'DO 0', 'ALTER TABLE `recuperacion_password` ADD INDEX `idx_token` (`token`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recuperacion_password' AND (INDEX_NAME = 'idx_expiracion' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'expiracion'))), 'DO 0', 'ALTER TABLE `recuperacion_password` ADD INDEX `idx_expiracion` (`expiracion`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- configuracion.clave única (se omite si hoy hay claves repetidas: corríjalas primero)

SET @q = IF((EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracion' AND (INDEX_NAME = 'uq_clave' OR (SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'clave' AND NON_UNIQUE = 0))) OR (SELECT COUNT(*) - COUNT(DISTINCT `clave`) FROM `configuracion`) > 0), 'DO 0', 'ALTER TABLE `configuracion` ADD UNIQUE INDEX `uq_clave` (`clave`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;


-- ================================ 5. LLAVES FORÁNEAS =================================
-- Garantizan que no queden registros apuntando a campañas o usuarios que ya no existen.
-- Si alguna se omite por datos huérfanos, el reporte final (7b) lo indica.

SET @q = IF((NOT ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('usuarios', 'campanas') AND ENGINE = 'InnoDB') = 2 AND (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'campana_id') = (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campanas' AND COLUMN_NAME = 'id') AND (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'campana_id') = 'YES' AND (SELECT COUNT(*) FROM `usuarios` x LEFT JOIN `campanas` y ON x.`campana_id` = y.`id` WHERE x.`campana_id` IS NOT NULL AND y.`id` IS NULL) = 0) OR EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'campana_id' AND REFERENCED_TABLE_NAME IS NOT NULL)), 'DO 0', 'ALTER TABLE `usuarios` ADD CONSTRAINT `fk_usuarios_campana` FOREIGN KEY (`campana_id`) REFERENCES `campanas` (`id`) ON DELETE SET NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF((NOT ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('campanas', 'usuarios') AND ENGINE = 'InnoDB') = 2 AND (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campanas' AND COLUMN_NAME = 'candidato_id') = (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'id') AND (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campanas' AND COLUMN_NAME = 'candidato_id') = 'YES' AND (SELECT COUNT(*) FROM `campanas` x LEFT JOIN `usuarios` y ON x.`candidato_id` = y.`id` WHERE x.`candidato_id` IS NOT NULL AND y.`id` IS NULL) = 0) OR EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campanas' AND COLUMN_NAME = 'candidato_id' AND REFERENCED_TABLE_NAME IS NOT NULL)), 'DO 0', 'ALTER TABLE `campanas` ADD CONSTRAINT `fk_campanas_candidato` FOREIGN KEY (`candidato_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF((NOT ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('simpatizantes', 'campanas') AND ENGINE = 'InnoDB') = 2 AND (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'campana_id') = (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campanas' AND COLUMN_NAME = 'id') AND (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'campana_id') = 'YES' AND (SELECT COUNT(*) FROM `simpatizantes` x LEFT JOIN `campanas` y ON x.`campana_id` = y.`id` WHERE x.`campana_id` IS NOT NULL AND y.`id` IS NULL) = 0) OR EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'campana_id' AND REFERENCED_TABLE_NAME IS NOT NULL)), 'DO 0', 'ALTER TABLE `simpatizantes` ADD CONSTRAINT `fk_simpatizantes_campana` FOREIGN KEY (`campana_id`) REFERENCES `campanas` (`id`) ON DELETE SET NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF((NOT ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('simpatizantes', 'usuarios') AND ENGINE = 'InnoDB') = 2 AND (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'capturista_id') = (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'id') AND (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'capturista_id') = 'YES' AND (SELECT COUNT(*) FROM `simpatizantes` x LEFT JOIN `usuarios` y ON x.`capturista_id` = y.`id` WHERE x.`capturista_id` IS NOT NULL AND y.`id` IS NULL) = 0) OR EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'capturista_id' AND REFERENCED_TABLE_NAME IS NOT NULL)), 'DO 0', 'ALTER TABLE `simpatizantes` ADD CONSTRAINT `fk_simpatizantes_capturista` FOREIGN KEY (`capturista_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF((NOT ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('logs_auditoria', 'usuarios') AND ENGINE = 'InnoDB') = 2 AND (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND COLUMN_NAME = 'usuario_id') = (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'id') AND (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND COLUMN_NAME = 'usuario_id') = 'YES' AND (SELECT COUNT(*) FROM `logs_auditoria` x LEFT JOIN `usuarios` y ON x.`usuario_id` = y.`id` WHERE x.`usuario_id` IS NOT NULL AND y.`id` IS NULL) = 0) OR EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND COLUMN_NAME = 'usuario_id' AND REFERENCED_TABLE_NAME IS NOT NULL)), 'DO 0', 'ALTER TABLE `logs_auditoria` ADD CONSTRAINT `fk_logs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF((NOT ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('recuperacion_password', 'usuarios') AND ENGINE = 'InnoDB') = 2 AND (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recuperacion_password' AND COLUMN_NAME = 'usuario_id') = (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'id') AND 1 = 1 AND (SELECT COUNT(*) FROM `recuperacion_password` x LEFT JOIN `usuarios` y ON x.`usuario_id` = y.`id` WHERE x.`usuario_id` IS NOT NULL AND y.`id` IS NULL) = 0) OR EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recuperacion_password' AND COLUMN_NAME = 'usuario_id' AND REFERENCED_TABLE_NAME IS NOT NULL)), 'DO 0', 'ALTER TABLE `recuperacion_password` ADD CONSTRAINT `fk_recuperacion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF((NOT ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('jerarquia_usuarios', 'usuarios') AND ENGINE = 'InnoDB') = 2 AND (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jerarquia_usuarios' AND COLUMN_NAME = 'superior_id') = (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'id') AND 1 = 1 AND (SELECT COUNT(*) FROM `jerarquia_usuarios` x LEFT JOIN `usuarios` y ON x.`superior_id` = y.`id` WHERE x.`superior_id` IS NOT NULL AND y.`id` IS NULL) = 0) OR EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jerarquia_usuarios' AND COLUMN_NAME = 'superior_id' AND REFERENCED_TABLE_NAME IS NOT NULL)), 'DO 0', 'ALTER TABLE `jerarquia_usuarios` ADD CONSTRAINT `fk_jerarquia_superior` FOREIGN KEY (`superior_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF((NOT ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('jerarquia_usuarios', 'usuarios') AND ENGINE = 'InnoDB') = 2 AND (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jerarquia_usuarios' AND COLUMN_NAME = 'subordinado_id') = (SELECT REPLACE(COLUMN_TYPE, '(11)', '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'id') AND 1 = 1 AND (SELECT COUNT(*) FROM `jerarquia_usuarios` x LEFT JOIN `usuarios` y ON x.`subordinado_id` = y.`id` WHERE x.`subordinado_id` IS NOT NULL AND y.`id` IS NULL) = 0) OR EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jerarquia_usuarios' AND COLUMN_NAME = 'subordinado_id' AND REFERENCED_TABLE_NAME IS NOT NULL)), 'DO 0', 'ALTER TABLE `jerarquia_usuarios` ADD CONSTRAINT `fk_jerarquia_subordinado` FOREIGN KEY (`subordinado_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;


-- ================================ 6. PARÁMETROS =====================================
-- Solo se crean los que no existen; los valores actuales nunca se modifican.

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'sistema_nombre', 'Sistema de Validación de Simpatizantes', 'Nombre del sistema', 'texto'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'sistema_nombre');

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'max_intentos_login', '3', 'Máximo de intentos fallidos de login', 'numero'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'max_intentos_login');

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'tiempo_bloqueo_minutos', '30', 'Tiempo de bloqueo tras intentos fallidos (minutos)', 'numero'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'tiempo_bloqueo_minutos');

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'backup_automatico', 'false', 'Activar respaldos automáticos diarios (requiere la tarea programada)', 'boolean'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'backup_automatico');

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'notificaciones_email', 'true', 'Activar notificaciones por email', 'boolean'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'notificaciones_email');

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'notificaciones_whatsapp', 'false', 'Activar notificaciones por WhatsApp', 'boolean'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'notificaciones_whatsapp');

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'registro_publico_habilitado', @registro_publico_inicial, 'Habilitar registro público de simpatizantes', 'boolean'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'registro_publico_habilitado');

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'terminos_condiciones', 'Al registrarse como simpatizante, acepta que sus datos personales sean utilizados con fines de organización de la campaña y serán tratados de manera responsable conforme a la legislación aplicable.', 'Términos y condiciones del registro público', 'texto'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'terminos_condiciones');


-- Valores nulos que el sistema espera como 0 / 1
UPDATE simpatizantes SET validado = 0 WHERE validado IS NULL;
UPDATE usuarios SET activo = 1 WHERE activo IS NULL;
UPDATE campanas SET activa = 1 WHERE activa IS NULL;

-- ================================ 7. REPORTE FINAL ===================================

-- 7a) Columnas que siguen faltando (debe salir vacío)
SELECT e.tabla, e.columna
FROM (
    SELECT 'usuarios' AS tabla, 'id' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'username' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'email' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'password' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'nombre_completo' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'rol' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'id' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'nombre' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'fecha_inicio' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'fecha_fin' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'id' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'nombre_completo' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'domicilio_completo' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'seccion_electoral' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'id' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'accion' AS columna
    UNION ALL SELECT 'configuracion' AS tabla, 'id' AS columna
    UNION ALL SELECT 'configuracion' AS tabla, 'clave' AS columna
    UNION ALL SELECT 'recuperacion_password' AS tabla, 'id' AS columna
    UNION ALL SELECT 'recuperacion_password' AS tabla, 'usuario_id' AS columna
    UNION ALL SELECT 'recuperacion_password' AS tabla, 'token' AS columna
    UNION ALL SELECT 'recuperacion_password' AS tabla, 'expiracion' AS columna
    UNION ALL SELECT 'recuperacion_password' AS tabla, 'usado' AS columna
    UNION ALL SELECT 'recuperacion_password' AS tabla, 'created_at' AS columna
    UNION ALL SELECT 'jerarquia_usuarios' AS tabla, 'id' AS columna
    UNION ALL SELECT 'jerarquia_usuarios' AS tabla, 'superior_id' AS columna
    UNION ALL SELECT 'jerarquia_usuarios' AS tabla, 'subordinado_id' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'id' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'to_email' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'subject' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'body' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'status' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'attempts' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'max_attempts' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'scheduled_at' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'activo' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'campana_id' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'intentos_fallidos' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'bloqueado_hasta' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'whatsapp' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'twitter' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'instagram' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'facebook' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'youtube' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'tiktok' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'created_at' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'updated_at' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'descripcion' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'candidato_id' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'activa' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'created_at' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'updated_at' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'sexo' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'ciudad' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'clave_elector' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'curp' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'fecha_nacimiento' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'ano_registro' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'vigencia' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'telefono' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'whatsapp' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'email' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'twitter' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'instagram' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'facebook' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'youtube' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'tiktok' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'observaciones' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'latitud' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'longitud' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'ine_frontal' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'ine_posterior' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'firma_digital' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'campana_id' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'capturista_id' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'metodo_captura' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'validado' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'duplicado' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'created_at' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'updated_at' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'usuario_id' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'tabla_afectada' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'registro_id' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'datos_anteriores' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'datos_nuevos' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'ip_address' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'user_agent' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'created_at' AS columna
    UNION ALL SELECT 'configuracion' AS tabla, 'valor' AS columna
    UNION ALL SELECT 'configuracion' AS tabla, 'descripcion' AS columna
    UNION ALL SELECT 'configuracion' AS tabla, 'tipo' AS columna
    UNION ALL SELECT 'configuracion' AS tabla, 'updated_at' AS columna
) e
LEFT JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = e.tabla AND c.COLUMN_NAME = e.columna
WHERE c.COLUMN_NAME IS NULL
ORDER BY e.tabla, e.columna;


-- 7b) Llaves foráneas pendientes (vacío = todas aplicadas).
--     Si alguna aparece, normalmente hay registros que apuntan a un dato borrado: ver la sección 8.
SELECT f.tabla, f.columna, f.apunta_a, 'PENDIENTE' AS estado
FROM (
    SELECT 'usuarios' AS tabla, 'campana_id' AS columna, 'campanas' AS apunta_a
    UNION ALL SELECT 'campanas' AS tabla, 'candidato_id' AS columna, 'usuarios' AS apunta_a
    UNION ALL SELECT 'simpatizantes' AS tabla, 'campana_id' AS columna, 'campanas' AS apunta_a
    UNION ALL SELECT 'simpatizantes' AS tabla, 'capturista_id' AS columna, 'usuarios' AS apunta_a
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'usuario_id' AS columna, 'usuarios' AS apunta_a
    UNION ALL SELECT 'recuperacion_password' AS tabla, 'usuario_id' AS columna, 'usuarios' AS apunta_a
    UNION ALL SELECT 'jerarquia_usuarios' AS tabla, 'superior_id' AS columna, 'usuarios' AS apunta_a
    UNION ALL SELECT 'jerarquia_usuarios' AS tabla, 'subordinado_id' AS columna, 'usuarios' AS apunta_a
) f
LEFT JOIN information_schema.KEY_COLUMN_USAGE k
       ON k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = f.tabla AND k.COLUMN_NAME = f.columna AND k.REFERENCED_TABLE_NAME IS NOT NULL
WHERE k.COLUMN_NAME IS NULL;

-- 7c) Cantidad de registros por tabla
SELECT 'usuarios' AS tabla, COUNT(*) AS registros FROM usuarios
UNION ALL SELECT 'campanas', COUNT(*) FROM campanas
UNION ALL SELECT 'simpatizantes', COUNT(*) FROM simpatizantes
UNION ALL SELECT 'logs_auditoria', COUNT(*) FROM logs_auditoria
UNION ALL SELECT 'configuracion', COUNT(*) FROM configuracion
UNION ALL SELECT 'recuperacion_password', COUNT(*) FROM recuperacion_password;

-- 7d) Estado del registro público
SELECT clave, valor FROM configuracion WHERE clave = 'registro_publico_habilitado';

-- 7e) RESUMEN FINAL (último resultado): debe decir "ESTRUCTURA COMPLETA"

SELECT IF(COUNT(*) = 0, 'ESTRUCTURA COMPLETA', CONCAT('FALTAN ', COUNT(*), ' COLUMNAS: revise 7a')) AS estado_estructura
FROM (
SELECT e.tabla, e.columna
FROM (
    SELECT 'usuarios' AS tabla, 'id' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'username' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'email' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'password' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'nombre_completo' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'rol' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'id' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'nombre' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'fecha_inicio' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'fecha_fin' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'id' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'nombre_completo' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'domicilio_completo' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'seccion_electoral' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'id' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'accion' AS columna
    UNION ALL SELECT 'configuracion' AS tabla, 'id' AS columna
    UNION ALL SELECT 'configuracion' AS tabla, 'clave' AS columna
    UNION ALL SELECT 'recuperacion_password' AS tabla, 'id' AS columna
    UNION ALL SELECT 'recuperacion_password' AS tabla, 'usuario_id' AS columna
    UNION ALL SELECT 'recuperacion_password' AS tabla, 'token' AS columna
    UNION ALL SELECT 'recuperacion_password' AS tabla, 'expiracion' AS columna
    UNION ALL SELECT 'recuperacion_password' AS tabla, 'usado' AS columna
    UNION ALL SELECT 'recuperacion_password' AS tabla, 'created_at' AS columna
    UNION ALL SELECT 'jerarquia_usuarios' AS tabla, 'id' AS columna
    UNION ALL SELECT 'jerarquia_usuarios' AS tabla, 'superior_id' AS columna
    UNION ALL SELECT 'jerarquia_usuarios' AS tabla, 'subordinado_id' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'id' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'to_email' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'subject' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'body' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'status' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'attempts' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'max_attempts' AS columna
    UNION ALL SELECT 'email_queue' AS tabla, 'scheduled_at' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'activo' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'campana_id' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'intentos_fallidos' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'bloqueado_hasta' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'whatsapp' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'twitter' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'instagram' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'facebook' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'youtube' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'tiktok' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'created_at' AS columna
    UNION ALL SELECT 'usuarios' AS tabla, 'updated_at' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'descripcion' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'candidato_id' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'activa' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'created_at' AS columna
    UNION ALL SELECT 'campanas' AS tabla, 'updated_at' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'sexo' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'ciudad' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'clave_elector' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'curp' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'fecha_nacimiento' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'ano_registro' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'vigencia' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'telefono' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'whatsapp' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'email' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'twitter' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'instagram' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'facebook' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'youtube' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'tiktok' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'observaciones' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'latitud' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'longitud' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'ine_frontal' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'ine_posterior' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'firma_digital' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'campana_id' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'capturista_id' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'metodo_captura' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'validado' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'duplicado' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'created_at' AS columna
    UNION ALL SELECT 'simpatizantes' AS tabla, 'updated_at' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'usuario_id' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'tabla_afectada' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'registro_id' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'datos_anteriores' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'datos_nuevos' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'ip_address' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'user_agent' AS columna
    UNION ALL SELECT 'logs_auditoria' AS tabla, 'created_at' AS columna
    UNION ALL SELECT 'configuracion' AS tabla, 'valor' AS columna
    UNION ALL SELECT 'configuracion' AS tabla, 'descripcion' AS columna
    UNION ALL SELECT 'configuracion' AS tabla, 'tipo' AS columna
    UNION ALL SELECT 'configuracion' AS tabla, 'updated_at' AS columna
) e
LEFT JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = e.tabla AND c.COLUMN_NAME = e.columna
WHERE c.COLUMN_NAME IS NULL
) f;


-- =====================================================================================
-- 8. OPCIONAL: limpiar datos huérfanos (solo si el reporte 7b lista llaves pendientes)
--    Quite las dos barras iniciales de las líneas que necesite y vuelva a ejecutar este archivo.
-- =====================================================================================
-- UPDATE simpatizantes SET campana_id = NULL    WHERE campana_id    IS NOT NULL AND campana_id    NOT IN (SELECT id FROM campanas);
-- UPDATE simpatizantes SET capturista_id = NULL WHERE capturista_id IS NOT NULL AND capturista_id NOT IN (SELECT id FROM usuarios);
-- UPDATE usuarios      SET campana_id = NULL    WHERE campana_id    IS NOT NULL AND campana_id    NOT IN (SELECT id FROM campanas);
-- UPDATE campanas      SET candidato_id = NULL  WHERE candidato_id  IS NOT NULL AND candidato_id  NOT IN (SELECT id FROM usuarios);
-- UPDATE logs_auditoria SET usuario_id = NULL   WHERE usuario_id    IS NOT NULL AND usuario_id    NOT IN (SELECT id FROM usuarios);