-- Actualización del Sistema de Simpatizantes
-- Nuevas funcionalidades y mejoras
-- Fecha: 2025-01-27
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar este script

-- =========================================================
-- 1. NUEVAS CONFIGURACIONES DEL SISTEMA
-- =========================================================

-- Configuración de correo electrónico del sistema
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'correo_sistema', '', 'Correo electrónico principal que envía los mensajes del sistema', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'correo_sistema');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'correo_sistema_nombre', 'Sistema de Simpatizantes', 'Nombre que aparece como remitente en los correos', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'correo_sistema_nombre');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'smtp_host', '', 'Servidor SMTP para envío de correos (ej: smtp.gmail.com)', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'smtp_host');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'smtp_puerto', '587', 'Puerto SMTP (587 para TLS, 465 para SSL)', 'numero'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'smtp_puerto');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'smtp_usuario', '', 'Usuario para autenticación SMTP', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'smtp_usuario');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'smtp_password', '', 'Contraseña para autenticación SMTP', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'smtp_password');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'smtp_seguridad', 'tls', 'Tipo de seguridad SMTP (tls o ssl)', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'smtp_seguridad');

-- Configuración de WhatsApp Chatbot
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'whatsapp_chatbot', '', 'Número de WhatsApp del chatbot del sistema (10 dígitos)', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'whatsapp_chatbot');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'whatsapp_api_token', '', 'Token de API para servicio de WhatsApp Business', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'whatsapp_api_token');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'whatsapp_habilitado', 'false', 'Habilitar integración con WhatsApp', 'boolean'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'whatsapp_habilitado');

-- Configuración de teléfonos de contacto
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'telefono_principal', '', 'Teléfono principal de contacto', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'telefono_principal');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'telefono_secundario', '', 'Teléfono secundario de contacto', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'telefono_secundario');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'telefono_emergencia', '', 'Teléfono de emergencia', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'telefono_emergencia');

-- Configuración de horarios de atención
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'horario_atencion', 'Lunes a Viernes: 9:00 AM - 6:00 PM\nSábados: 9:00 AM - 2:00 PM', 'Horarios de atención al público', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'horario_atencion');

-- Configuración de dirección
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'direccion_oficina', '', 'Dirección física de la oficina principal', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'direccion_oficina');

-- Configuraciones generales adicionales
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'sitio_web', '', 'URL del sitio web oficial', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'sitio_web');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'facebook_page', '', 'URL de la página de Facebook', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'facebook_page');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'twitter_handle', '', 'Usuario de Twitter/X (sin @)', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'twitter_handle');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'instagram_handle', '', 'Usuario de Instagram (sin @)', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'instagram_handle');

-- Configuración de seguridad
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'sesion_timeout', '3600', 'Tiempo de expiración de sesión en segundos (por defecto 1 hora)', 'numero'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'sesion_timeout');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'password_expira_dias', '90', 'Días antes de que expire una contraseña (0 = nunca)', 'numero'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'password_expira_dias');

-- Configuración de respaldos
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'backup_ruta', '/backups', 'Ruta donde se almacenan los respaldos', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'backup_ruta');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'backup_hora', '02:00', 'Hora de ejecución del respaldo automático (formato 24h)', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'backup_hora');

-- Configuración de notificaciones
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'notificar_nuevo_registro', 'true', 'Enviar notificación cuando se registra un nuevo simpatizante', 'boolean'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'notificar_nuevo_registro');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'notificar_validacion', 'true', 'Enviar notificación cuando se valida un simpatizante', 'boolean'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'notificar_validacion');

-- Configuración de la organización
INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'nombre_organizacion', '', 'Nombre de la organización o partido', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'nombre_organizacion');

INSERT INTO configuracion (clave, valor, descripcion, tipo) 
SELECT 'eslogan_organizacion', '', 'Eslogan o frase representativa', 'texto'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'eslogan_organizacion');

-- =========================================================
-- 2. VERIFICACIÓN Y REPORTE
-- =========================================================

SELECT 'Actualización completada exitosamente' AS mensaje;

-- Contar las nuevas configuraciones agregadas
SELECT COUNT(*) AS 'Configuraciones Totales' FROM configuracion;

-- Mostrar las nuevas configuraciones
SELECT 
    clave, 
    CASE 
        WHEN LENGTH(valor) > 50 THEN CONCAT(SUBSTRING(valor, 1, 50), '...')
        ELSE valor
    END AS valor,
    tipo,
    descripcion
FROM configuracion
WHERE clave IN (
    'correo_sistema', 'correo_sistema_nombre', 'smtp_host', 'smtp_puerto', 'smtp_usuario', 'smtp_password', 'smtp_seguridad',
    'whatsapp_chatbot', 'whatsapp_api_token', 'whatsapp_habilitado',
    'telefono_principal', 'telefono_secundario', 'telefono_emergencia',
    'horario_atencion', 'direccion_oficina',
    'sitio_web', 'facebook_page', 'twitter_handle', 'instagram_handle',
    'sesion_timeout', 'password_expira_dias',
    'backup_ruta', 'backup_hora',
    'notificar_nuevo_registro', 'notificar_validacion',
    'nombre_organizacion', 'eslogan_organizacion'
)
ORDER BY clave;

-- =========================================================
-- NOTAS IMPORTANTES
-- =========================================================
-- 
-- 1. Este script es idempotente - puede ejecutarse múltiples veces sin causar errores
-- 2. Usa INSERT ... SELECT ... WHERE NOT EXISTS para evitar duplicados
-- 3. Después de ejecutar, configure los valores desde la interfaz web en:
--    Sistema > Configuración
-- 
-- CONFIGURACIONES CRÍTICAS A COMPLETAR:
-- - correo_sistema: Email del remitente para notificaciones
-- - smtp_host, smtp_puerto, smtp_usuario, smtp_password: Credenciales SMTP
-- - whatsapp_chatbot: Número de WhatsApp para comunicación
-- - telefono_principal: Teléfono principal de contacto
-- - horario_atencion: Horarios de atención al público
-- 
-- INSTRUCCIONES POST-INSTALACIÓN:
-- 1. Acceder como Super Admin a Configuración del Sistema
-- 2. Completar los campos de correo electrónico
-- 3. Configurar el número de WhatsApp del chatbot
-- 4. Establecer teléfonos de contacto y horarios
-- 5. Configurar redes sociales y sitio web
-- 6. Probar el envío de notificaciones
-- 
-- =========================================================
