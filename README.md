# Sistema de Validación de Simpatizantes

Sistema integral de gestión y validación de simpatizantes con mapa de calor, desarrollado en PHP puro con arquitectura MVC.

## 🚀 Características Principales

### Módulos Implementados

#### 1. **Autenticación y Seguridad**
- Sistema de login multi-nivel con roles diferenciados
- Encriptación de contraseñas con `password_hash()`
- Bloqueo automático tras intentos fallidos
- Gestión de sesiones seguras
- Roles: Super Admin, Admin, Candidato, Coordinador, Capturista

#### 2. **Gestión de Simpatizantes**
- **Captura Manual**: Formulario completo con validación en tiempo real
- **Campos incluidos**:
  - Datos personales (nombre, domicilio, sexo, ciudad)
  - Información electoral (clave de elector, CURP, sección)
  - Datos de contacto (WhatsApp, email)
  - Redes sociales (Twitter, Instagram, Facebook, YouTube, TikTok)
  - Ubicación geográfica (latitud/longitud con detección automática)
  - Documentos (INE frontal/posterior, firma digital)
- **Validaciones**:
  - Formato CURP (18 caracteres)
  - Formato Clave de Elector
  - Validación de email
  - Detección de duplicados
- Búsqueda y filtrado avanzado
- Paginación de resultados
- Exportación a Excel (pendiente)

#### 3. **Mapa de Calor**
- Visualización geográfica con Leaflet.js
- Mapa de calor con gradiente de colores
- Marcadores interactivos con información
- Filtros por campaña y rango de fechas
- Detección automática de ubicación

#### 4. **Sistema de Reportes**
- Dashboard con estadísticas en tiempo real
- Gráficas con Chart.js
- Estadísticas por sección electoral
- Métricas por capturista
- Reportes por campaña

#### 5. **Gestión de Usuarios**
- CRUD completo de usuarios
- Asignación de roles
- Jerarquía de usuarios (coordinador-capturista)
- Gestión de permisos por rol

#### 6. **Gestión de Campañas**
- Creación y administración de campañas
- Asociación con candidatos
- Estadísticas por campaña
- Estado activo/inactivo

#### 7. **Auditoría y Logs**
- Registro de todas las acciones del sistema
- Tracking de IP y User Agent
- Histórico de cambios
- Reportes de auditoría

## 🛠️ Tecnologías Utilizadas

- **Backend**: PHP 7.4+ (puro, sin frameworks)
- **Base de Datos**: MySQL 5.7+
- **Frontend**: 
  - HTML5, CSS3, JavaScript
  - Bootstrap 5.3 (diseño responsivo)
  - Bootstrap Icons
- **Gráficas**: Chart.js
- **Mapas**: Leaflet.js + Leaflet.heat
- **Arquitectura**: MVC (Modelo-Vista-Controlador)

## 📋 Requisitos del Sistema

- Apache 2.4+
- PHP 7.4+ con extensiones:
  - PDO
  - pdo_mysql
  - mbstring
  - json
- MySQL 5.7+
- mod_rewrite habilitado

## 🔧 Instalación

### 1. Clonar el Repositorio

```bash
git clone https://github.com/danjohn007/Simpatizantes.git
cd Simpatizantes
```

### 2. Configurar la Base de Datos

1. Crear la base de datos:

```bash
mysql -u root -p
```

```sql
CREATE DATABASE simpatizantes_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Importar el esquema:

```bash
mysql -u root -p simpatizantes_db < database/schema.sql
```

### 3. Configurar la Aplicación

Editar el archivo `config/config.php` con tus credenciales:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'simpatizantes_db');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
```

### 4. Configurar Permisos

```bash
chmod 755 -R public/uploads
chown www-data:www-data -R public/uploads
```

### 5. Configurar Apache

**Opción A: Virtual Host (Recomendado)**

Crear archivo en `/etc/apache2/sites-available/simpatizantes.conf`:

```apache
<VirtualHost *:80>
    ServerName simpatizantes.local
    DocumentRoot /ruta/a/Simpatizantes
    
    <Directory /ruta/a/Simpatizantes>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/simpatizantes_error.log
    CustomLog ${APACHE_LOG_DIR}/simpatizantes_access.log combined
</VirtualHost>
```

Habilitar el sitio:

```bash
sudo a2ensite simpatizantes.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

**Opción B: Subdirectorio**

Simplemente copiar el proyecto en `/var/www/html/simpatizantes/`

### 6. Verificar Instalación

Acceder a: `http://localhost/test-conexion.php`

Este test verificará:
- ✅ Configuración de constantes
- ✅ Conexión a MySQL
- ✅ Existencia de tablas
- ✅ Usuario de prueba
- ✅ Directorios de carga
- ✅ Extensiones PHP

## 🔑 Credenciales de Acceso

El sistema incluye usuarios de prueba con contraseña: `admin123`

| Usuario | Rol | Email |
|---------|-----|-------|
| `superadmin` | Super Admin | superadmin@sistema.com |
| `admin1` | Administrador | admin1@sistema.com |
| `candidato1` | Candidato | candidato1@sistema.com |
| `coordinador1` | Coordinador | coordinador1@sistema.com |
| `capturista1` | Capturista | capturista1@sistema.com |

## 📱 Uso del Sistema

### Acceso al Sistema

1. Acceder a la URL base del sistema
2. Iniciar sesión con las credenciales
3. El sistema redirigirá al dashboard según el rol

### Captura de Simpatizantes

#### Método Manual:
1. Ir a **Simpatizantes → Nuevo Simpatizante**
2. Llenar el formulario con los datos requeridos
3. Campos obligatorios: Nombre completo, Domicilio, Sección electoral
4. Usar botón "Detectar Ubicación" para geolocalización automática
5. Guardar el registro

#### Método de Escaneo:
1. Subir imagen de INE frontal
2. Subir imagen de INE posterior
3. Capturar firma digital
4. El sistema validará los documentos
5. Completar campos faltantes
6. Guardar el registro

### Visualización de Datos

#### Dashboard:
- Estadísticas generales
- Gráficas de avance
- Acciones rápidas
- Top secciones

#### Mapa de Calor:
- Ver distribución geográfica
- Filtrar por campaña
- Filtrar por fechas
- Ver detalles por ubicación

#### Reportes:
- Generar reportes por período
- Filtrar por campaña
- Exportar a Excel/PDF
- Estadísticas por capturista

## 📁 Estructura del Proyecto

```
Simpatizantes/
├── app/
│   ├── controllers/      # Controladores MVC
│   │   ├── AuthController.php
│   │   └── SimpatizanteController.php
│   ├── models/           # Modelos de datos
│   │   ├── Usuario.php
│   │   ├── Simpatizante.php
│   │   ├── Campana.php
│   │   └── LogAuditoria.php
│   └── views/
│       └── layouts/      # Plantillas
│           ├── header.php
│           └── footer.php
├── config/
│   ├── config.php        # Configuración principal
│   └── Database.php      # Clase de base de datos
├── database/
│   └── schema.sql        # Esquema de base de datos
├── public/
│   ├── css/              # Estilos personalizados
│   ├── js/               # Scripts JavaScript
│   ├── uploads/          # Archivos subidos
│   ├── simpatizantes/    # Módulo de simpatizantes
│   ├── reportes/         # Módulo de reportes
│   ├── usuarios/         # Módulo de usuarios
│   ├── campanas/         # Módulo de campañas
│   ├── dashboard.php     # Dashboard principal
│   └── mapa-calor.php    # Mapa de calor
├── .htaccess             # Configuración Apache
├── index.php             # Página de login
├── test-conexion.php     # Test de instalación
└── README.md             # Este archivo
```

## 🔒 Seguridad

El sistema implementa las siguientes medidas de seguridad:

- ✅ Encriptación de contraseñas con `password_hash()`
- ✅ Prevención de SQL Injection con PDO prepared statements
- ✅ Protección CSRF en formularios
- ✅ Validación de datos en servidor
- ✅ Bloqueo temporal tras intentos fallidos
- ✅ Sesiones seguras con timeout
- ✅ Control de acceso basado en roles
- ✅ Registro de auditoría completo
- ✅ Protección de archivos sensibles con .htaccess
- ✅ Validación de tipos de archivo en uploads

## 🎯 Características Destacadas

### URL Base Automática
El sistema detecta automáticamente la URL base, permitiendo instalación en cualquier directorio sin configuración manual.

### Validación en Tiempo Real
- Formato de CURP
- Formato de Clave de Elector
- Email válido
- Detección de duplicados

### Geolocalización
Detección automática de ubicación del navegador para registro preciso de coordenadas.

### Responsive Design
Interfaz completamente adaptable a dispositivos móviles, tablets y escritorio.

## 📊 Módulos Futuros (Roadmap)

- [ ] OCR automático para extracción de datos del INE
- [ ] Validación de autenticidad del documento INE
- [ ] Importación masiva desde Excel
- [ ] Exportación de reportes a PDF
- [ ] Notificaciones por WhatsApp/Email
- [ ] Respaldos automáticos de BD
- [ ] API REST para integración externa
- [ ] App móvil nativa

## 🤝 Contribución

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crear una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir un Pull Request

## 📝 Licencia

Este proyecto es de código abierto y está disponible bajo la Licencia MIT.

## 👥 Autor

Sistema desarrollado para gestión electoral y validación de simpatizantes.

## 🐛 Reporte de Bugs

Si encuentras algún bug, por favor crea un issue en GitHub con:
- Descripción del problema
- Pasos para reproducirlo
- Comportamiento esperado vs actual
- Screenshots si es posible

## 📞 Soporte

Para soporte técnico:
- Crear un issue en GitHub
- Revisar la documentación en este README
- Verificar el test de conexión en `/test-conexion.php`

---

**Versión:** 1.0.0  
**Última actualización:** 2024  
**Estado:** En desarrollo activo
