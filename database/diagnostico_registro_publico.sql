-- ============================================================
-- DIAGNÓSTICO DEL REGISTRO PÚBLICO (solo lectura)
-- Seleccione la base de datos en phpMyAdmin, pestaña SQL, pegue y ejecute.
-- phpMyAdmin mostrará el resultado de cada consulta.
-- ============================================================

-- 1) ¿Existe el parámetro y está activado?  Debe verse valor = true
SELECT clave, valor, tipo
FROM configuracion
WHERE clave IN ('registro_publico_habilitado', 'terminos_condiciones');
-- Si no devuelve la fila 'registro_publico_habilitado', el registro está apagado
-- (el sistema lo trata como deshabilitado). Se corrige con migracion_servidor.sql.

-- 2) ¿El campo metodo_captura acepta el valor 'web'?
SELECT COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'metodo_captura';
-- Debe incluir 'web', por ejemplo: enum('manual','escaneo','app','web')
-- Si solo dice enum('manual','escaneo'), el registro público falla al guardar.
