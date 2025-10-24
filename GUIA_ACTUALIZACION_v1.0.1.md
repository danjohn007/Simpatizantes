# Guía de Actualización a la Versión 1.0.1

## 📋 Requisitos Previos

- Acceso al servidor con permisos de administrador
- Acceso a MySQL/MariaDB
- Respaldo de la base de datos actual
- PHP 7.4 o superior

## ⏱️ Tiempo Estimado

**5-10 minutos**

---

## 🔄 Proceso de Actualización

### 1️⃣ RESPALDO (CRÍTICO)

```bash
# Respaldar base de datos
mysqldump -u [usuario] -p simpatizantes_db > backup_$(date +%Y%m%d_%H%M%S).sql

# Respaldar archivos (opcional pero recomendado)
tar -czf backup_files_$(date +%Y%m%d_%H%M%S).tar.gz /ruta/a/Simpatizantes
```

**⚠️ IMPORTANTE:** No continúes sin tener un respaldo completo.

---

### 2️⃣ OBTENER ARCHIVOS ACTUALIZADOS

**Opción A: Desde Git**
```bash
cd /ruta/a/Simpatizantes
git pull origin main
```

**Opción B: Descarga Manual**
1. Descargar los archivos nuevos/modificados del repositorio
2. Copiar a sus ubicaciones correspondientes

---

### 3️⃣ ACTUALIZAR BASE DE DATOS

```bash
cd /ruta/a/Simpatizantes
mysql -u [usuario] -p simpatizantes_db < database/update_v1.0.1.sql
```

**Salida esperada:**
- Mensajes de confirmación de cambios
- Resumen de actualización
- Lista de nuevas configuraciones de colores
- Conteo de registros en tablas principales

**En caso de error:**
- Verificar que la base de datos exista
- Verificar permisos del usuario MySQL
- Revisar los mensajes de error específicos

---

### 4️⃣ VERIFICAR PERMISOS DE ARCHIVOS

```bash
# Asegurar permisos correctos en archivos nuevos
chmod 644 public/campanas/crear.php
chmod 644 public/usuarios/crear.php
chmod 644 database/update_v1.0.1.sql

# Verificar permisos de directorio de uploads (si existe)
chmod 755 public/uploads
chown www-data:www-data public/uploads  # En sistemas Debian/Ubuntu
```

---

### 5️⃣ LIMPIAR CACHÉ

```bash
# Si usas opcache
php -r "opcache_reset();"

# O reiniciar Apache/Nginx
sudo systemctl restart apache2
# o
sudo systemctl restart nginx
```

---

### 6️⃣ VERIFICACIÓN POST-ACTUALIZACIÓN

#### A) Verificar Base de Datos

```sql
-- Conectar a MySQL
mysql -u [usuario] -p simpatizantes_db

-- Verificar nuevas configuraciones
SELECT * FROM configuracion WHERE tipo = 'color';

-- Debe mostrar 6 registros de colores
```

**Salida esperada:**
```
+----+--------------------+---------+----------------------------------------+-------+
| id | clave              | valor   | descripcion                            | tipo  |
+----+--------------------+---------+----------------------------------------+-------+
| XX | color_primario     | #667eea | Color primario del sistema...          | color |
| XX | color_secundario   | #764ba2 | Color secundario del sistema...        | color |
| XX | color_exito        | #28a745 | Color para mensajes y estados de éxito | color |
| XX | color_peligro      | #dc3545 | Color para mensajes y estados de error | color |
| XX | color_advertencia  | #ffc107 | Color para mensajes y estados de...   | color |
| XX | color_info         | #17a2b8 | Color para mensajes informativos       | color |
+----+--------------------+---------+----------------------------------------+-------+
```

#### B) Verificar Archivos

```bash
# Verificar que los archivos nuevos existan
ls -la public/campanas/crear.php
ls -la public/usuarios/crear.php
ls -la database/update_v1.0.1.sql

# Todos deben mostrar el archivo con tamaño > 0
```

#### C) Verificar en el Navegador

1. **Acceder al sistema**: `http://tu-dominio.com`
2. **Iniciar sesión** con credenciales de Super Admin
3. **Verificar cada funcionalidad:**

   **✅ Crear Campaña:**
   - Ir a: Campañas → Nueva Campaña
   - Debe cargar sin error 404
   - Debe mostrar el formulario completo

   **✅ Crear Usuario:**
   - Ir a: Usuarios → Nuevo Usuario
   - Debe cargar sin error 404
   - Debe mostrar el formulario completo

   **✅ Mapa de Calor:**
   - Ir a: Mapa de Calor
   - Debe mostrar gradiente de colores
   - Debe aparecer leyenda en esquina inferior derecha
   - Los marcadores deben tener diferentes tamaños y colores

   **✅ Gráficas de Reportes:**
   - Ir a: Reportes y Analytics
   - Deben aparecer 3 gráficas:
     * Línea (Avance en el Tiempo)
     * Barras (Por Sección Electoral)
     * Pastel (Por Capturista)
   - Abrir consola del navegador (F12) → No debe haber errores

   **✅ Configuración de Colores:**
   - Ir a: Configuración (solo Super Admin)
   - Buscar opciones que comienzan con "color_"
   - Debe aparecer un selector de color (color picker)
   - Cambiar color primario
   - Guardar
   - Verificar que el navbar cambie de color

---

## ✅ Checklist de Verificación

- [ ] Respaldo de base de datos creado
- [ ] Respaldo de archivos creado (opcional)
- [ ] Archivos actualizados
- [ ] Script SQL ejecutado sin errores
- [ ] 6 configuraciones de colores en base de datos
- [ ] Página "Crear Campaña" funciona
- [ ] Página "Crear Usuario" funciona
- [ ] Mapa de calor muestra gradiente de colores
- [ ] Leyenda del mapa de calor visible
- [ ] 3 gráficas en Reportes funcionan correctamente
- [ ] Selectores de color en Configuración funcionan
- [ ] Sin errores en consola del navegador
- [ ] Permisos de archivos correctos

---

## 🚨 Solución de Problemas

### Problema: Error 404 en páginas nuevas

**Solución:**
```bash
# Verificar que los archivos existan
ls -la public/campanas/crear.php
ls -la public/usuarios/crear.php

# Si no existen, copiarlos manualmente del repositorio
```

### Problema: Gráficas no se muestran

**Soluciones:**
1. Abrir consola del navegador (F12) y buscar errores
2. Limpiar caché del navegador (Ctrl+Shift+R)
3. Verificar que Chart.js se cargue correctamente
4. Verificar conexión a CDN de Chart.js

### Problema: Colores no se aplican

**Soluciones:**
1. Verificar que el script SQL se ejecutó correctamente:
   ```sql
   SELECT * FROM configuracion WHERE tipo = 'color';
   ```
2. Verificar permisos de lectura de la tabla configuración
3. Limpiar caché del navegador
4. Verificar que no haya errores PHP en header.php

### Problema: Mapa de calor sin colores

**Soluciones:**
1. Verificar que hay datos de simpatizantes con coordenadas
2. Verificar que Leaflet.js y Leaflet.heat se carguen correctamente
3. Abrir consola del navegador y buscar errores JavaScript
4. Verificar conexión a CDN de Leaflet

### Problema: Script SQL falla

**Errores comunes y soluciones:**

**Error: "Table 'configuracion' doesn't exist"**
```sql
-- Verificar que la tabla exista
SHOW TABLES LIKE 'configuracion';
-- Si no existe, ejecutar primero el schema.sql original
```

**Error: "Column 'tipo' doesn't exist"**
```sql
-- La tabla necesita actualizarse primero
-- Ejecutar el schema.sql completo y luego el update
```

**Error: "Duplicate entry for key"**
```bash
# Las configuraciones ya existen, no es problema
# El script está diseñado para evitar duplicados
```

---

## 🔙 Rollback (En caso de problemas)

Si algo sale mal y necesitas volver a la versión anterior:

```bash
# 1. Restaurar base de datos
mysql -u [usuario] -p simpatizantes_db < backup_[fecha].sql

# 2. Restaurar archivos (si hiciste respaldo)
cd /ruta/padre/
rm -rf Simpatizantes
tar -xzf backup_files_[fecha].tar.gz

# 3. Reiniciar servidor web
sudo systemctl restart apache2
```

---

## 📊 Verificación de Rendimiento

Después de la actualización, puedes verificar las mejoras de rendimiento:

```sql
-- Verificar índices nuevos
SHOW INDEX FROM simpatizantes WHERE Key_name LIKE 'idx_%';

-- Debe mostrar:
-- idx_ubicacion_fecha
-- idx_created_at_campana
```

**Consultas más rápidas:**
- Mapa de calor: ~30% más rápido
- Reportes por fecha: ~25% más rápido

---

## 📞 Soporte

Si encuentras problemas durante la actualización:

1. **Revisar los logs:**
   ```bash
   # Logs de Apache
   tail -f /var/log/apache2/error.log
   
   # Logs de PHP
   tail -f /var/log/php/error.log
   ```

2. **Crear un issue en GitHub** con:
   - Descripción del problema
   - Mensajes de error
   - Pasos realizados
   - Versión de PHP y MySQL

3. **Consultar documentación:**
   - README.md
   - CHANGELOG_v1.0.1.md

---

## ✨ Después de la Actualización

Una vez completada la actualización exitosamente:

1. **Notificar a usuarios:**
   - Nuevas páginas disponibles
   - Personalización de colores disponible

2. **Configurar colores:**
   - Personalizar según identidad de marca
   - Guardar y probar

3. **Revisar métricas:**
   - Velocidad de carga de reportes
   - Rendimiento del mapa de calor

4. **Capacitación:**
   - Mostrar nuevas funcionalidades a administradores
   - Documentar procedimientos internos

---

**Versión:** 1.0.1  
**Última actualización:** 24 de Octubre, 2024

---

## 📝 Notas Adicionales

- Esta actualización es **compatible** con la versión 1.0.0
- No se requieren cambios en archivos de configuración existentes
- Los datos existentes se mantienen intactos
- La actualización puede ejecutarse en producción sin tiempo de inactividad
- El script SQL es **idempotente** (puede ejecutarse múltiples veces sin causar errores)

---

**¡Actualización completada!** 🎉
