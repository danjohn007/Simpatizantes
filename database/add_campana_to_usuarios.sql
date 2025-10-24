-- Migración: Agregar campo campana_id a tabla usuarios
-- Fecha: 2025-10-24

USE simpatizantes_db;

-- Agregar columna campana_id a la tabla usuarios
ALTER TABLE usuarios 
ADD COLUMN campana_id INT NULL AFTER rol,
ADD FOREIGN KEY (campana_id) REFERENCES campanas(id) ON DELETE SET NULL,
ADD INDEX idx_campana (campana_id);

-- Comentario: 
-- Los usuarios con rol super_admin y admin pueden ver todas las campañas
-- Los demás roles (candidato, coordinador, capturista) solo verán datos de su campaña asignada
