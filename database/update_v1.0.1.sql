-- Actualización de Base de Datos v1.0.1
-- Sistema de Validación de Simpatizantes
-- Fecha: 2024-10-24

-- ===============================================
-- IMPORTANTE: Ejecutar este script con precaución
-- Se recomienda hacer un respaldo de la base de datos antes de ejecutar
-- ===============================================

USE simpatizantes_db;

-- ===============================================
-- 1. AGREGAR TIPO DE CONFIGURACIÓN 'color'
-- ===============================================

-- Modificar el enum de la tabla configuracion para incluir 'color'
ALTER TABLE configuracion 
MODIFY COLUMN tipo ENUM('texto', 'numero', 'boolean', 'json', 'color') DEFAULT 'texto';

-- ===============================================
-- 2. AGREGAR CONFIGURACIONES DE COLORES
-- ===============================================

-- Insertar configuraciones de colores principales si no existen
INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'color_primario', '#667eea', 'Color primario del sistema (usado en navbar, botones, enlaces)', 'color'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'color_primario');

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'color_secundario', '#764ba2', 'Color secundario del sistema (usado en degradados)', 'color'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'color_secundario');

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'color_exito', '#28a745', 'Color para mensajes y estados de éxito', 'color'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'color_exito');

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'color_peligro', '#dc3545', 'Color para mensajes y estados de error', 'color'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'color_peligro');

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'color_advertencia', '#ffc107', 'Color para mensajes y estados de advertencia', 'color'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'color_advertencia');

INSERT INTO configuracion (clave, valor, descripcion, tipo)
SELECT 'color_info', '#17a2b8', 'Color para mensajes informativos', 'color'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'color_info');

-- ===============================================
-- 3. VERIFICAR INTEGRIDAD DE LAS TABLAS
-- ===============================================

-- Verificar que las tablas principales existen y tienen la estructura correcta
-- Esta es una verificación, no modifica datos

SELECT 
    'Tabla usuarios' as tabla,
    COUNT(*) as total_registros
FROM usuarios
UNION ALL
SELECT 
    'Tabla campanas' as tabla,
    COUNT(*) as total_registros
FROM campanas
UNION ALL
SELECT 
    'Tabla simpatizantes' as tabla,
    COUNT(*) as total_registros
FROM simpatizantes
UNION ALL
SELECT 
    'Tabla configuracion' as tabla,
    COUNT(*) as total_registros
FROM configuracion
UNION ALL
SELECT 
    'Tabla logs_auditoria' as tabla,
    COUNT(*) as total_registros
FROM logs_auditoria;

-- ===============================================
-- 4. AGREGAR ÍNDICES ADICIONALES PARA MEJORAR RENDIMIENTO
-- ===============================================

-- Índice para mejorar el rendimiento del mapa de calor
-- Solo se crea si no existe
SET @indexExists = (
    SELECT COUNT(1) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() 
    AND table_name = 'simpatizantes' 
    AND index_name = 'idx_ubicacion_fecha'
);

SET @sqlStatement = IF(
    @indexExists = 0,
    'CREATE INDEX idx_ubicacion_fecha ON simpatizantes(latitud, longitud, created_at)',
    'SELECT "Index idx_ubicacion_fecha already exists" AS msg'
);

PREPARE stmt FROM @sqlStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para mejorar el rendimiento de reportes por fecha
SET @indexExists = (
    SELECT COUNT(1) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() 
    AND table_name = 'simpatizantes' 
    AND index_name = 'idx_created_at_campana'
);

SET @sqlStatement = IF(
    @indexExists = 0,
    'CREATE INDEX idx_created_at_campana ON simpatizantes(created_at, campana_id)',
    'SELECT "Index idx_created_at_campana already exists" AS msg'
);

PREPARE stmt FROM @sqlStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===============================================
-- 5. LIMPIAR Y OPTIMIZAR TABLAS
-- ===============================================

-- Optimizar las tablas principales para mejorar el rendimiento
OPTIMIZE TABLE usuarios;
OPTIMIZE TABLE campanas;
OPTIMIZE TABLE simpatizantes;
OPTIMIZE TABLE configuracion;
OPTIMIZE TABLE logs_auditoria;

-- ===============================================
-- 6. RESUMEN DE CAMBIOS
-- ===============================================

SELECT '================================================' AS '';
SELECT 'RESUMEN DE ACTUALIZACIÓN v1.0.1' AS '';
SELECT '================================================' AS '';
SELECT '' AS '';
SELECT 'Cambios aplicados:' AS '';
SELECT '1. Tipo de configuración "color" agregado' AS '';
SELECT '2. 6 nuevas configuraciones de colores agregadas' AS '';
SELECT '3. Índices de rendimiento optimizados' AS '';
SELECT '4. Tablas optimizadas' AS '';
SELECT '' AS '';
SELECT 'La actualización se completó exitosamente.' AS '';
SELECT '================================================' AS '';

-- Mostrar las nuevas configuraciones de colores
SELECT 
    'NUEVAS CONFIGURACIONES DE COLORES:' AS '';
    
SELECT 
    clave as 'Clave',
    valor as 'Valor',
    descripcion as 'Descripción'
FROM configuracion 
WHERE tipo = 'color'
ORDER BY id;
