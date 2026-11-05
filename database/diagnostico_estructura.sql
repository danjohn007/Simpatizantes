-- ============================================================
-- DIAGNÓSTICO DE ESTRUCTURA
-- Lista las columnas que el sistema necesita y que NO existen
-- en la base de datos actual.
--
-- Uso: en phpMyAdmin, seleccione la base de datos del sistema,
-- abra la pestaña SQL, pegue este contenido y pulse "Continuar".
-- Si el resultado está vacío, la estructura está completa.
-- Es solo de lectura: no modifica nada.
-- ============================================================

SELECT esperado.tabla AS tabla_con_problema, esperado.columna AS columna_que_falta
FROM (
    SELECT 'usuarios' AS tabla, 'campana_id' AS columna
    UNION ALL SELECT 'usuarios', 'intentos_fallidos'
    UNION ALL SELECT 'usuarios', 'bloqueado_hasta'
    UNION ALL SELECT 'usuarios', 'whatsapp'
    UNION ALL SELECT 'usuarios', 'twitter'
    UNION ALL SELECT 'usuarios', 'instagram'
    UNION ALL SELECT 'usuarios', 'facebook'
    UNION ALL SELECT 'usuarios', 'youtube'
    UNION ALL SELECT 'usuarios', 'tiktok'
    UNION ALL SELECT 'simpatizantes', 'telefono'
    UNION ALL SELECT 'simpatizantes', 'observaciones'
    UNION ALL SELECT 'simpatizantes', 'ciudad'
    UNION ALL SELECT 'simpatizantes', 'ano_registro'
    UNION ALL SELECT 'simpatizantes', 'vigencia'
    UNION ALL SELECT 'simpatizantes', 'ine_frontal'
    UNION ALL SELECT 'simpatizantes', 'ine_posterior'
    UNION ALL SELECT 'simpatizantes', 'firma_digital'
    UNION ALL SELECT 'simpatizantes', 'campana_id'
    UNION ALL SELECT 'simpatizantes', 'capturista_id'
    UNION ALL SELECT 'simpatizantes', 'metodo_captura'
    UNION ALL SELECT 'simpatizantes', 'validado'
    UNION ALL SELECT 'campanas', 'candidato_id'
    UNION ALL SELECT 'campanas', 'activa'
    UNION ALL SELECT 'recuperacion_password', 'token'
    UNION ALL SELECT 'recuperacion_password', 'expiracion'
    UNION ALL SELECT 'recuperacion_password', 'usado'
    UNION ALL SELECT 'logs_auditoria', 'datos_anteriores'
    UNION ALL SELECT 'logs_auditoria', 'datos_nuevos'
    UNION ALL SELECT 'logs_auditoria', 'user_agent'
    UNION ALL SELECT 'configuracion', 'tipo'
) AS esperado
LEFT JOIN information_schema.COLUMNS c
       ON c.TABLE_SCHEMA = DATABASE()
      AND c.TABLE_NAME   = esperado.tabla
      AND c.COLUMN_NAME  = esperado.columna
WHERE c.COLUMN_NAME IS NULL
ORDER BY esperado.tabla, esperado.columna;
