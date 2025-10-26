-- Database Schema for Sistema de Validación de Simpatizantes
-- MySQL 5.7

CREATE DATABASE IF NOT EXISTS simpatizantes_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE simpatizantes_db;

-- Tabla de Usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    rol ENUM('super_admin', 'admin', 'candidato', 'coordinador', 'capturista') NOT NULL DEFAULT 'capturista',
    activo TINYINT(1) DEFAULT 1,
    intentos_fallidos INT DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    whatsapp VARCHAR(20),
    twitter VARCHAR(100),
    instagram VARCHAR(100),
    facebook VARCHAR(100),
    youtube VARCHAR(100),
    tiktok VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rol (rol),
    INDEX idx_email (email),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Campañas
CREATE TABLE IF NOT EXISTS campanas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    candidato_id INT,
    activa TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidato_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_activa (activa),
    INDEX idx_fechas (fecha_inicio, fecha_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Simpatizantes
CREATE TABLE IF NOT EXISTS simpatizantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(150) NOT NULL,
    domicilio_completo TEXT NOT NULL,
    sexo ENUM('M', 'F', 'O') NULL,
    ciudad VARCHAR(100),
    clave_elector VARCHAR(18),
    curp VARCHAR(18),
    fecha_nacimiento DATE,
    ano_registro INT,
    vigencia INT,
    seccion_electoral VARCHAR(10) NOT NULL,
    whatsapp VARCHAR(20),
    email VARCHAR(100),
    twitter VARCHAR(100),
    instagram VARCHAR(100),
    facebook VARCHAR(100),
    youtube VARCHAR(100),
    tiktok VARCHAR(100),
    observaciones TEXT,
    latitud DECIMAL(10, 8),
    longitud DECIMAL(11, 8),
    ine_frontal VARCHAR(255),
    ine_posterior VARCHAR(255),
    firma_digital VARCHAR(255),
    campana_id INT,
    capturista_id INT,
    metodo_captura ENUM('manual', 'escaneo') DEFAULT 'manual',
    validado TINYINT(1) DEFAULT 0,
    duplicado TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (campana_id) REFERENCES campanas(id) ON DELETE SET NULL,
    FOREIGN KEY (capturista_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_clave_elector (clave_elector),
    INDEX idx_curp (curp),
    INDEX idx_seccion (seccion_electoral),
    INDEX idx_campana (campana_id),
    INDEX idx_capturista (capturista_id),
    INDEX idx_ubicacion (latitud, longitud),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Relaciones de Jerarquía (para coordinadores y capturistas)
CREATE TABLE IF NOT EXISTS jerarquia_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    superior_id INT NOT NULL,
    subordinado_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (superior_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (subordinado_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_relacion (superior_id, subordinado_id),
    INDEX idx_superior (superior_id),
    INDEX idx_subordinado (subordinado_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Logs de Auditoría
CREATE TABLE IF NOT EXISTS logs_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    accion VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(50),
    registro_id INT,
    datos_anteriores TEXT,
    datos_nuevos TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Respaldos
CREATE TABLE IF NOT EXISTS respaldos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    tamano_bytes BIGINT,
    tipo ENUM('automatico', 'manual') DEFAULT 'automatico',
    usuario_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Configuración del Sistema
CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) UNIQUE NOT NULL,
    valor TEXT,
    descripcion TEXT,
    tipo ENUM('texto', 'numero', 'boolean', 'json') DEFAULT 'texto',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Sesiones
CREATE TABLE IF NOT EXISTS sesiones (
    id VARCHAR(128) PRIMARY KEY,
    usuario_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    data TEXT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar datos de ejemplo

-- Usuario Super Admin (password: admin123)
INSERT INTO usuarios (username, email, password, nombre_completo, rol, activo) VALUES
('superadmin', 'superadmin@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrador', 'super_admin', 1),
('admin1', 'admin1@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador Principal', 'admin', 1),
('candidato1', 'candidato1@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Pérez García', 'candidato', 1),
('coordinador1', 'coordinador1@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'María López Sánchez', 'coordinador', 1),
('capturista1', 'capturista1@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlos Ramírez Torres', 'capturista', 1),
('capturista2', 'capturista2@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ana Martínez Cruz', 'capturista', 1);

-- Campañas de ejemplo
INSERT INTO campanas (nombre, descripcion, fecha_inicio, fecha_fin, candidato_id, activa) VALUES
('Campaña Municipal 2024', 'Campaña para elección municipal', '2024-01-01', '2024-06-30', 3, 1),
('Campaña Estatal 2024', 'Campaña para elección estatal', '2024-02-01', '2024-07-31', 3, 1);

-- Jerarquía de usuarios
INSERT INTO jerarquia_usuarios (superior_id, subordinado_id) VALUES
(4, 5),  -- coordinador1 -> capturista1
(4, 6);  -- coordinador1 -> capturista2

-- Simpatizantes de ejemplo
INSERT INTO simpatizantes (nombre_completo, domicilio_completo, sexo, ciudad, clave_elector, curp, fecha_nacimiento, ano_registro, vigencia, seccion_electoral, whatsapp, email, latitud, longitud, campana_id, capturista_id, metodo_captura, validado) VALUES
('Roberto González Méndez', 'Calle Reforma 123, Col. Centro', 'M', 'Ciudad de México', 'GOMERO85010112H100', 'GOMR850101HDFLNB07', '1985-01-01', 2010, 2027, '0123', '5551234567', 'roberto.gonzalez@email.com', 19.4326, -99.1332, 1, 5, 'manual', 1),
('Laura Patricia Hernández', 'Av. Juárez 456, Col. Moderna', 'F', 'Guadalajara', 'HELAPA90020223M200', 'HEHL900202MJCLRR03', '1990-02-02', 2012, 2026, '0234', '5559876543', 'laura.hernandez@email.com', 20.6597, -103.3496, 1, 5, 'manual', 1),
('José Luis Rodríguez Silva', 'Boulevard Independencia 789', 'M', 'Monterrey', 'ROSLJO88030334H300', 'ROSL880303HNLDSS01', '1988-03-03', 2011, 2028, '0345', '5551122334', 'jose.rodriguez@email.com', 25.6866, -100.3161, 1, 6, 'escaneo', 1),
('Carmen Sofía Martínez Ruiz', 'Calle Hidalgo 321, Centro', 'F', 'Puebla', 'MARUCA92040445F400', 'MARC920404MPLTRS08', '1992-04-04', 2013, 2029, '0456', '5554433221', 'carmen.martinez@email.com', 19.0414, -98.2063, 2, 6, 'manual', 1),
('Francisco Javier López Torres', 'Av. Universidad 654', 'M', 'Querétaro', 'LOTOFR87050556H500', 'LOTF870505HQTPRR09', '1987-05-05', 2010, 2025, '0567', '5556677889', 'francisco.lopez@email.com', 20.5888, -100.3899, 2, 5, 'manual', 0);

-- Configuración inicial del sistema
INSERT INTO configuracion (clave, valor, descripcion, tipo) VALUES
('sistema_nombre', 'Sistema de Validación de Simpatizantes', 'Nombre del sistema', 'texto'),
('max_intentos_login', '3', 'Máximo de intentos fallidos de login', 'numero'),
('tiempo_bloqueo_minutos', '30', 'Tiempo de bloqueo tras intentos fallidos (minutos)', 'numero'),
('backup_automatico', 'true', 'Activar respaldos automáticos diarios', 'boolean'),
('notificaciones_email', 'true', 'Activar notificaciones por email', 'boolean'),
('notificaciones_whatsapp', 'false', 'Activar notificaciones por WhatsApp', 'boolean');

-- Logs de auditoría de ejemplo
INSERT INTO logs_auditoria (usuario_id, accion, tabla_afectada, registro_id, ip_address, user_agent) VALUES
(5, 'crear_simpatizante', 'simpatizantes', 1, '192.168.1.100', 'Mozilla/5.0'),
(5, 'crear_simpatizante', 'simpatizantes', 2, '192.168.1.100', 'Mozilla/5.0'),
(6, 'crear_simpatizante', 'simpatizantes', 3, '192.168.1.101', 'Mozilla/5.0');
