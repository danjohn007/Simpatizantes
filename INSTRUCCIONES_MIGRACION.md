# Instrucciones de Migración

## Correcciones aplicadas

### 1. Corrección del menú lateral en dispositivos móviles
El menú lateral ahora funciona como un sidebar overlay en dispositivos móviles:
- Se muestra al hacer clic en el botón de menú (☰) en la esquina superior izquierda
- Se cierra automáticamente al hacer clic fuera del menú o al seleccionar una opción
- En pantallas grandes (tablets y desktop) el menú se mantiene siempre visible

### 2. Corrección del error al editar simpatizantes
Se ha agregado la columna `observaciones` que faltaba en la tabla de simpatizantes.

## Aplicar la migración de base de datos

Para aplicar la corrección del error de edición, ejecuta el siguiente comando SQL en tu base de datos:

```bash
mysql -u tu_usuario -p simpatizantes_db < database/add_observaciones_column.sql
```

O si prefieres hacerlo desde phpMyAdmin u otro cliente SQL:

```sql
USE simpatizantes_db;

ALTER TABLE simpatizantes 
ADD COLUMN IF NOT EXISTS observaciones TEXT AFTER tiktok;
```

**Nota:** Este cambio es seguro y no afectará los datos existentes. La nueva columna se agregará al final de la tabla.

## Verificar los cambios

1. **Menú móvil**: 
   - Abre el sistema en un navegador móvil o usa las herramientas de desarrollador (F12) para simular un dispositivo móvil
   - Verifica que aparece el botón de menú (☰) en la esquina superior izquierda
   - Haz clic y verifica que el menú se desliza desde la izquierda

2. **Edición de simpatizantes**:
   - Después de aplicar la migración SQL
   - Intenta editar un simpatizante existente
   - El error "Column not found: 1054 Unknown column 'observaciones'" ya no debería aparecer

## Archivos modificados

- `app/views/layouts/header.php` - Sidebar responsive con overlay
- `app/views/layouts/footer.php` - JavaScript para toggle del sidebar
- `database/schema.sql` - Actualizado para nuevas instalaciones
- `database/add_observaciones_column.sql` - Migración para bases de datos existentes
