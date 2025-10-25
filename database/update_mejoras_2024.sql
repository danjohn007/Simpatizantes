-- Actualización del Sistema de Simpatizantes (versión alternativa sin INFORMATION_SCHEMA)
-- Fecha: 2024
-- Nota: este script evita consultas a INFORMATION_SCHEMA para no necesitar permisos adicionales.
--       Usa procedimientos con CONTINUE HANDLER para ignorar errores cuando el objeto ya existe.
--       Ejecutar desde CLI: mysql -u usuario -p basedatos < update_mejoras_2024.sql
--       Hacer backup antes de ejecutar en producción.

-- 0. Ajustes de seguridad y recomendaciones
-- - Si prefieres, ejecuta solo las secciones necesarias en un entorno de pruebas primero.
-- - Los handlers silencian errores dentro del procedimiento: revisa los logs si algo no se aplica.

-- 1. Tabla para recuperación de contraseñas
CREATE TABLE IF NOT EXISTS recuperacion_password (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expiracion DATETIME NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expiracion (expiracion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Agregar configuraciones del sistema si no existen

-- Configuración de registro público
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'registro_publico_habilitado', 'true', 'Habilitar registro público de simpatizantes', 'boolean'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'registro_publico_habilitado');

-- Términos y condiciones
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'terminos_condiciones', 'Al registrarse como simpatizante, acepta que sus datos personales sean utilizados con fines electorales y de organización política. Sus datos serán tratados de manera responsable conforme a la legislación aplicable.', 'Términos y condiciones por defecto', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'terminos_condiciones');

-- Configuración de API OCR
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'ocr_api_key', '', 'API Key para servicio OCR (ej: OCR.space, Tesseract)', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'ocr_api_key');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'ocr_api_url', 'https://api.ocr.space/parse/image', 'URL del servicio OCR', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'ocr_api_url');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'ocr_habilitado', 'false', 'Habilitar extracción OCR de INE', 'boolean'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'ocr_habilitado');

-- 3. Agregar columna telefono a simpatizantes si no existe (método portable usando handler)
DELIMITER $$
DROP PROCEDURE IF EXISTS add_col_telefono$$
CREATE PROCEDURE add_col_telefono()
BEGIN
  -- Ignorar cualquier excepción (por ejemplo: columna ya existe)
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  ALTER TABLE simpatizantes
    ADD COLUMN telefono VARCHAR(20) AFTER seccion_electoral;
END$$
CALL add_col_telefono()$$
DROP PROCEDURE IF EXISTS add_col_telefono$$
DELIMITER ;

-- 4. Agregar índices para mejorar rendimiento (ignorar si ya existen)
DELIMITER $$
DROP PROCEDURE IF EXISTS add_idxs_simpatizantes$$
CREATE PROCEDURE add_idxs_simpatizantes()
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  ALTER TABLE simpatizantes ADD INDEX idx_telefono (telefono);
  ALTER TABLE simpatizantes ADD INDEX idx_metodo_captura (metodo_captura);
END$$
CALL add_idxs_simpatizantes()$$
DROP PROCEDURE IF EXISTS add_idxs_simpatizantes$$
DELIMITER ;

-- 5. Agregar columna campana_id a usuarios si no existe y la FK (ignorar si ya existen)
DELIMITER $$
DROP PROCEDURE IF EXISTS add_campana_usuarios$$
CREATE PROCEDURE add_campana_usuarios()
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  ALTER TABLE usuarios ADD COLUMN campana_id INT AFTER rol;
  ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_campana FOREIGN KEY (campana_id) REFERENCES campanas(id) ON DELETE SET NULL;
  ALTER TABLE usuarios ADD INDEX idx_campana (campana_id);
END$$
CALL add_campana_usuarios()$$
DROP PROCEDURE IF EXISTS add_campana_usuarios$$
DELIMITER ;

-- 6. Actualizar configuraciones de notificaciones
UPDATE configuracion 
SET descripcion = 'Activar notificaciones por email para registro de simpatizantes'
WHERE clave = 'notificaciones_email';

UPDATE configuracion 
SET descripcion = 'Activar notificaciones por WhatsApp para registro de simpatizantes'
WHERE clave = 'notificaciones_whatsapp';

-- 7. Eliminar usuarios de prueba (OPCIONAL - Descomentar para ejecutar)
-- IMPORTANTE: Solo ejecutar después de crear un nuevo usuario admin

-- DELETE FROM usuarios WHERE username IN ('coordinador1', 'capturista1', 'capturista2', 'admin1', 'candidato1');
-- Solo mantener el superadmin o crear uno nuevo antes de eliminar los demás

-- Para crear un nuevo superadmin (cambiar los valores según necesidad):
-- INSERT INTO usuarios (username, email, password, nombre_completo, rol, activo) 
-- VALUES ('admin', 'admin@tudominio.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador del Sistema', 'super_admin', 1);
-- La contraseña del ejemplo es: admin123

-- 8. Agregar configuración para logo del sistema
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'logo_sistema', '', 'URL o ruta del logo del sistema', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'logo_sistema');

-- 9. Agregar configuración para colores personalizados
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'color_primario', '#667eea', 'Color primario del sistema', 'color'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'color_primario');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'color_secundario', '#764ba2', 'Color secundario del sistema', 'color'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'color_secundario');

-- 10. Agregar configuración para límites de registros
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'max_registros_por_dia', '100', 'Máximo de registros permitidos por día por capturista', 'numero'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'max_registros_por_dia');

-- 11. Actualizar comentarios y metadata
-- (Si necesitas cambiar el ENUM y existen valores fuera de la nueva lista, ALTER puede fallar)
DELIMITER $$
DROP PROCEDURE IF EXISTS modify_metodo_captura$$
CREATE PROCEDURE modify_metodo_captura()
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  ALTER TABLE simpatizantes 
    MODIFY COLUMN metodo_captura ENUM('manual', 'escaneo', 'app', 'web') DEFAULT 'manual' COMMENT 'Método de captura del registro';
END$$
CALL modify_metodo_captura()$$
DROP PROCEDURE IF EXISTS modify_metodo_captura$$
DELIMITER ;

-- 12. Verificar y mostrar configuración actualizada
SELECT 'Actualización completada exitosamente' AS mensaje;

-- Verificar tablas
SELECT 
    'configuracion' AS tabla,
    COUNT(*) AS registros
FROM configuracion
UNION ALL
SELECT 
    'recuperacion_password' AS tabla,
    COUNT(*) AS registros
FROM recuperacion_password;

-- Mostrar configuraciones agregadas
SELECT clave, valor, descripcion, tipo
FROM configuracion
WHERE clave IN (
    'registro_publico_habilitado',
    'terminos_condiciones',
    'ocr_api_key',
    'ocr_api_url',
    'ocr_habilitado',
    'logo_sistema',
    'color_primario',
    'color_secundario',
    'max_registros_por_dia'
)
ORDER BY clave;

-- NOTAS IMPORTANTES:
-- 1. Este script intenta ser seguro para ejecutar múltiples veces (idempotente) y no requiere SELECT sobre information_schema.
-- 2. Los procedimientos usan CONTINUE HANDLER FOR SQLEXCEPTION para ignorar errores como "columna ya existe" o "índice ya existe".
--    Eso también silencirá otros errores internos del bloque; ejecutar primero en entorno de pruebas y revisar logs.
-- 3. Hacer backup de la base de datos antes de ejecutar en producción.
-- 4. Revisar la sección de ENUM metodo_captura si hay valores en la tabla que no están en la nueva lista.
-- 5. Descomentar la sección de eliminación solo después de crear nuevos usuarios admin.

-- INSTRUCCIONES POST-INSTALACIÓN:
-- 1. Acceder a Configuración en el sistema
-- 2. Configurar términos y condiciones
-- 3. Si se usa OCR, configurar API key
-- 4. Ajustar colores y límites según necesidad
-- 5. Crear usuarios nuevos antes de eliminar los de prueba
