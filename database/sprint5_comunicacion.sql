-- =====================================================================================
-- SPRINT 5 · COMUNICACIÓN  ·  Sistema de Validación de Simpatizantes  ·  MySQL / MariaDB
--
-- Qué hace:
--   1. `contactos_simpatizante`: bitácora de cada llamada, visita, WhatsApp o correo a un simpatizante.
--   2. En `simpatizantes`: consentimiento para recibir mensajes, baja, y fecha del último contacto.
--   3. `plantillas_mensaje`: textos editables de correo y WhatsApp (con 6 plantillas iniciales).
--
-- Es SEGURO ejecutarlo varias veces. No borra ni modifica datos existentes: los simpatizantes ya
-- registrados quedan con el consentimiento "sin registrar" (no reciben correo masivo hasta que se
-- registre). Sin procedimientos ni DELIMITER.
-- Requisito: haber ejecutado antes migracion_servidor.sql y los de Sprint 2, 3 y 4.
-- ANTES: respaldo (phpMyAdmin > Exportar). Luego: phpMyAdmin > su base > SQL > pegar > Continuar.
-- =====================================================================================

SET NAMES utf8mb4;

SELECT DATABASE() AS base_de_datos, VERSION() AS version_mysql;

-- 1. Bitácora de contactos ----------------------------------------------------------------

CREATE TABLE IF NOT EXISTS contactos_simpatizante (
    id INT AUTO_INCREMENT PRIMARY KEY,
    simpatizante_id INT NOT NULL,
    usuario_id INT NULL,                  -- quién hizo el contacto (NULL = el propio simpatizante, p. ej. una baja)
    canal VARCHAR(20) NOT NULL,           -- llamada, visita, whatsapp, correo, otro
    resultado VARCHAR(30) NOT NULL,       -- contesto, no_contesto, interesado, no_interesado, volver_a_llamar, enviado, baja, otro
    nota VARCHAR(500) NULL,
    plantilla VARCHAR(50) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_simpatizante (simpatizante_id, created_at),
    INDEX idx_usuario (usuario_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Consentimiento y último contacto ------------------------------------------------------------

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'consentimiento'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `consentimiento` TINYINT(1) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'consentimiento_fecha'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `consentimiento_fecha` DATETIME NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'baja_comunicacion'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `baja_comunicacion` TINYINT(1) NOT NULL DEFAULT 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'baja_token'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `baja_token` CHAR(32) NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'ultimo_contacto'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD COLUMN `ultimo_contacto` DATETIME NULL');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND INDEX_NAME = 'uq_baja_token'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD UNIQUE INDEX `uq_baja_token` (`baja_token`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND INDEX_NAME = 'idx_ultimo_contacto'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_ultimo_contacto` (`ultimo_contacto`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q = IF(EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND INDEX_NAME = 'idx_consentimiento'), 'DO 0', 'ALTER TABLE `simpatizantes` ADD INDEX `idx_consentimiento` (`consentimiento`, `baja_comunicacion`)');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- 3. Plantillas de mensajes -----------------------------------------------------------------

CREATE TABLE IF NOT EXISTS plantillas_mensaje (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    canal VARCHAR(20) NOT NULL,           -- correo o whatsapp
    asunto VARCHAR(150) NULL,
    cuerpo TEXT NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO plantillas_mensaje (clave, nombre, canal, asunto, cuerpo)
SELECT 'bienvenida_correo', 'Bienvenida', 'correo', 'Gracias por sumarte, {{primer_nombre}}', 'Hola {{primer_nombre}},

Gracias por registrarte como simpatizante de {{campana}}. Tu apoyo nos da fuerza para seguir adelante.

Pronto te compartiremos información de las actividades en tu zona (sección {{seccion}}).

Un saludo,
Equipo de {{campana}}

Si ya no deseas recibir mensajes, puedes darte de baja aquí: {{enlace_baja}}'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plantillas_mensaje WHERE clave = 'bienvenida_correo');

INSERT INTO plantillas_mensaje (clave, nombre, canal, asunto, cuerpo)
SELECT 'bienvenida_whatsapp', 'Bienvenida', 'whatsapp', NULL, 'Hola {{primer_nombre}}, gracias por registrarte con {{campana}}. Soy {{capturista}}. Si tienes cualquier duda o propuesta, aquí estoy para escucharte.'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plantillas_mensaje WHERE clave = 'bienvenida_whatsapp');

INSERT INTO plantillas_mensaje (clave, nombre, canal, asunto, cuerpo)
SELECT 'recordatorio_correo', 'Seguimiento', 'correo', '{{primer_nombre}}, queremos saber de ti', 'Hola {{primer_nombre}},

Hace tiempo que no platicamos y queremos saber cómo estás y si hay algo que podamos hacer por tu colonia o tu familia.

Responde este correo y con gusto te atendemos.

Equipo de {{campana}}

Darte de baja: {{enlace_baja}}'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plantillas_mensaje WHERE clave = 'recordatorio_correo');

INSERT INTO plantillas_mensaje (clave, nombre, canal, asunto, cuerpo)
SELECT 'recordatorio_whatsapp', 'Seguimiento', 'whatsapp', NULL, 'Hola {{primer_nombre}}, soy {{capturista}} de {{campana}}. Hace tiempo que no platicamos; ¿cómo has estado? ¿Hay algo en tu colonia que debamos atender?'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plantillas_mensaje WHERE clave = 'recordatorio_whatsapp');

INSERT INTO plantillas_mensaje (clave, nombre, canal, asunto, cuerpo)
SELECT 'invitacion_evento_correo', 'Invitación a evento', 'correo', 'Te invitamos, {{primer_nombre}}', 'Hola {{primer_nombre}},

Te invitamos a nuestro próximo encuentro con vecinos de {{ciudad}}.

[Escribe aquí la fecha, la hora y el lugar]

Tu presencia es muy importante para nosotros.

Equipo de {{campana}}

Darte de baja: {{enlace_baja}}'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plantillas_mensaje WHERE clave = 'invitacion_evento_correo');

INSERT INTO plantillas_mensaje (clave, nombre, canal, asunto, cuerpo)
SELECT 'invitacion_evento_whatsapp', 'Invitación a evento', 'whatsapp', NULL, 'Hola {{primer_nombre}}, te invitamos a nuestro próximo encuentro con vecinos. [Escribe aquí la fecha, la hora y el lugar]. ¡Cuento contigo! — {{capturista}}, {{campana}}'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plantillas_mensaje WHERE clave = 'invitacion_evento_whatsapp');

-- 4. Reporte final ------------------------------------------------------------------------

SELECT
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes'
        AND COLUMN_NAME IN ('consentimiento','consentimiento_fecha','baja_comunicacion','baja_token','ultimo_contacto')) AS columnas_de_5,
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('contactos_simpatizante','plantillas_mensaje')) AS tablas_de_2,
    (SELECT COUNT(*) FROM plantillas_mensaje) AS plantillas,
    CASE WHEN
        (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes'
            AND COLUMN_NAME IN ('consentimiento','consentimiento_fecha','baja_comunicacion','baja_token','ultimo_contacto')) = 5
        AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('contactos_simpatizante','plantillas_mensaje')) = 2
    THEN 'SPRINT 5 LISTO' ELSE 'REVISAR: falta algo, ejecute el script de nuevo' END AS resultado;
