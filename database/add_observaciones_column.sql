-- Add observaciones column to simpatizantes table
-- This migration adds the missing observaciones column that is used in the edit functionality

USE simpatizantes_db;

-- Add observaciones column if it doesn't exist
ALTER TABLE simpatizantes 
ADD COLUMN IF NOT EXISTS observaciones TEXT AFTER tiktok;
