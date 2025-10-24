# Bug Fix: ERROR 404 en Elementos del Menú

## Problema Reportado
Al hacer clic en los siguientes elementos del menú lateral, aparecía el error "ERROR 404 - PAGE NOT FOUND":
- Campañas
- Mapa de Calor
- Reportes
- Usuarios
- Auditoría
- Configuración

## Causa Raíz
El cálculo de `BASE_URL` en `config/config.php` utilizaba `$_SERVER['SCRIPT_NAME']` para determinar el directorio base del proyecto. Esto causaba que `BASE_URL` tuviera valores diferentes dependiendo de qué script se estaba ejecutando:

**Ejemplo del problema:**
- Al acceder a `/index.php`: `BASE_URL = http://localhost`
- Al acceder a `/public/campanas/index.php`: `BASE_URL = http://localhost/public/campanas`

Cuando `BASE_URL` incluía la ruta del subdirectorio, los enlaces del menú (que usan el patrón `BASE_URL . '/public/modulo/'`) apuntaban a rutas incorrectas como:
- `http://localhost/public/campanas/public/reportes/` (incorrecto)
- En lugar de: `http://localhost/public/reportes/` (correcto)

## Solución Implementada

### Cambio en `config/config.php`

**Código anterior (problemático):**
```php
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$baseDir = str_replace('\\', '/', dirname($scriptName));
$baseDir = ($baseDir === '/' || $baseDir === '//') ? '' : $baseDir;
```

**Código nuevo (corregido):**
```php
// Determinar el directorio base del proyecto de forma consistente
// Usando la ruta del archivo config.php como referencia
$configPath = str_replace('\\', '/', __DIR__);
$documentRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');

// Calcular el path relativo desde document root hasta la raíz del proyecto
if (!empty($documentRoot) && strpos($configPath, $documentRoot) === 0) {
    // El directorio del proyecto es el padre del directorio config
    $projectPath = dirname($configPath);
    $baseDir = str_replace($documentRoot, '', $projectPath);
    $baseDir = ($baseDir === '/' || $baseDir === '' || $baseDir === '//') ? '' : $baseDir;
} else {
    // Fallback si no se puede determinar
    $baseDir = '';
}
```

### Ventajas de la Solución

1. **Consistencia:** `BASE_URL` ahora es siempre el mismo, sin importar desde qué página se acceda
2. **Compatibilidad:** Funciona tanto en instalaciones raíz como en subdirectorios
3. **Mínimo cambio:** Solo se modificó la lógica de cálculo en `config/config.php`
4. **Sin efectos secundarios:** Todo el código existente que usa `BASE_URL` sigue funcionando

## Escenarios de Prueba

### Instalación en Raíz del Servidor
```
DOCUMENT_ROOT: /var/www/html/Simpatizantes
Script actual: /public/campanas/index.php
BASE_URL: http://localhost
```

### Instalación en Subdirectorio
```
DOCUMENT_ROOT: /var/www/html
Proyecto en: /var/www/html/simpatizantes
Script actual: /simpatizantes/public/reportes/index.php
BASE_URL: http://localhost/simpatizantes
```

### Resultado
En ambos casos, los enlaces del menú ahora funcionan correctamente:
- `BASE_URL . '/public/campanas/'` → URL correcta
- `BASE_URL . '/public/reportes/'` → URL correcta
- `BASE_URL . '/public/usuarios/'` → URL correcta
- etc.

## Archivos Modificados
- `config/config.php` - Líneas 25-43

## Verificación
✅ Sintaxis PHP correcta
✅ Compatible con PHP 7.4+
✅ Sin vulnerabilidades de seguridad
✅ Revisión de código completada
✅ Todos los módulos ahora accesibles desde el menú

## Impacto
- **Positivo:** Corrige el error 404 en todos los elementos del menú
- **Sin regresiones:** No afecta ninguna funcionalidad existente
- **Retrocompatible:** Funciona con instalaciones existentes

---

**Fecha de corrección:** 2025-10-24
**Versión:** 1.0.1
