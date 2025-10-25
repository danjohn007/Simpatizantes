-- Actualización del Sistema de Simpatizantes
-- Fecha: 2024
-- Descripción: Actualizaciones y mejoras del sistema

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
SELECT 'terminos_condiciones', 'Al registrarse como simpatizante, acepta que sus datos personales sean utilizados con fines electorales y de organización política. Sus datos serán tratados de manera confidencial y conforme a la Ley de Protección de Datos Personales. Puede solicitar la eliminación de sus datos en cualquier momento contactando al administrador del sistema.', 'Términos y condiciones para registro público', 'texto'
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

-- 3. Agregar columna telefono a simpatizantes si no existe
-- (Ya existe en el esquema actual, esta línea es por seguridad)
ALTER TABLE simpatizantes 
ADD COLUMN IF NOT EXISTS telefono VARCHAR(20) AFTER seccion_electoral;

-- 4. Agregar índices para mejorar rendimiento
ALTER TABLE simpatizantes 
ADD INDEX IF NOT EXISTS idx_telefono (telefono);

ALTER TABLE simpatizantes 
ADD INDEX IF NOT EXISTS idx_metodo_captura (metodo_captura);

-- 5. Agregar columna campana_id a usuarios si no existe
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS campana_id INT AFTER rol;

ALTER TABLE usuarios
ADD CONSTRAINT IF NOT EXISTS fk_usuarios_campana 
FOREIGN KEY (campana_id) REFERENCES campanas(id) ON DELETE SET NULL;

ALTER TABLE usuarios
ADD INDEX IF NOT EXISTS idx_campana (campana_id);

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
ALTER TABLE simpatizantes 
MODIFY COLUMN metodo_captura ENUM('manual', 'escaneo', 'app', 'web') DEFAULT 'manual' COMMENT 'Método de captura del registro';

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
-- 1. Este script es seguro para ejecutar múltiples veces (idempotente)
-- 2. Hacer backup de la base de datos antes de ejecutar en producción
-- 3. Revisar y ajustar los valores por defecto según necesidades
-- 4. Los usuarios de prueba NO se eliminan automáticamente por seguridad
-- 5. Descomentar la sección de eliminación solo después de crear nuevos usuarios admin

-- INSTRUCCIONES POST-INSTALACIÓN:
-- 1. Acceder a Configuración en el sistema
-- 2. Configurar términos y condiciones
-- 3. Si se usa OCR, configurar API key
-- 4. Ajustar colores y límites según necesidad
-- 5. Crear usuarios nuevos antes de eliminar los de prueba
