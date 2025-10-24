#!/bin/bash

# Script de Instalación
# Sistema de Validación de Simpatizantes

echo "========================================="
echo "Sistema de Validación de Simpatizantes"
echo "Script de Instalación Automática"
echo "========================================="
echo ""

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Verificar si se está ejecutando como root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Este script debe ejecutarse como root${NC}"
    echo "Ejecuta: sudo ./install.sh"
    exit 1
fi

echo -e "${GREEN}✓${NC} Verificando requisitos del sistema..."
echo ""

# Verificar Apache
if ! command -v apache2 &> /dev/null; then
    echo -e "${RED}✗${NC} Apache no está instalado"
    echo "Instalando Apache..."
    apt-get update
    apt-get install -y apache2
else
    echo -e "${GREEN}✓${NC} Apache está instalado"
fi

# Verificar PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}✗${NC} PHP no está instalado"
    echo "Instalando PHP y extensiones necesarias..."
    apt-get install -y php php-mysql php-mbstring php-json php-curl
else
    PHP_VERSION=$(php -r 'echo PHP_VERSION;')
    echo -e "${GREEN}✓${NC} PHP $PHP_VERSION está instalado"
fi

# Verificar MySQL
if ! command -v mysql &> /dev/null; then
    echo -e "${RED}✗${NC} MySQL no está instalado"
    echo "Instalando MySQL..."
    apt-get install -y mysql-server
else
    echo -e "${GREEN}✓${NC} MySQL está instalado"
fi

echo ""
echo "========================================="
echo "Configuración de la Base de Datos"
echo "========================================="
echo ""

read -p "Nombre de la base de datos [simpatizantes_db]: " DB_NAME
DB_NAME=${DB_NAME:-simpatizantes_db}

read -p "Usuario MySQL [root]: " DB_USER
DB_USER=${DB_USER:-root}

read -sp "Contraseña MySQL: " DB_PASS
echo ""

read -p "Host MySQL [localhost]: " DB_HOST
DB_HOST=${DB_HOST:-localhost}

echo ""
echo -e "${GREEN}✓${NC} Configuración de BD recibida"
echo ""

# Crear base de datos
echo "Creando base de datos..."
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Base de datos creada"
else
    echo -e "${RED}✗${NC} Error al crear la base de datos"
    exit 1
fi

# Importar esquema
echo "Importando esquema de base de datos..."
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/schema.sql

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Esquema importado correctamente"
else
    echo -e "${RED}✗${NC} Error al importar el esquema"
    exit 1
fi

# Actualizar archivo de configuración
echo "Actualizando archivo de configuración..."
sed -i "s/define('DB_HOST', 'localhost');/define('DB_HOST', '$DB_HOST');/" config/config.php
sed -i "s/define('DB_NAME', 'simpatizantes_db');/define('DB_NAME', '$DB_NAME');/" config/config.php
sed -i "s/define('DB_USER', 'root');/define('DB_USER', '$DB_USER');/" config/config.php
sed -i "s/define('DB_PASS', '');/define('DB_PASS', '$DB_PASS');/" config/config.php

echo -e "${GREEN}✓${NC} Configuración actualizada"
echo ""

# Configurar permisos
echo "Configurando permisos de directorios..."
chmod 755 -R public/
chmod 777 -R public/uploads/
chown -R www-data:www-data public/uploads/

echo -e "${GREEN}✓${NC} Permisos configurados"
echo ""

# Habilitar mod_rewrite
echo "Habilitando mod_rewrite de Apache..."
a2enmod rewrite

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} mod_rewrite habilitado"
else
    echo -e "${YELLOW}⚠${NC} mod_rewrite ya estaba habilitado"
fi

# Reiniciar Apache
echo "Reiniciando Apache..."
systemctl restart apache2

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Apache reiniciado"
else
    echo -e "${RED}✗${NC} Error al reiniciar Apache"
fi

echo ""
echo "========================================="
echo "¡Instalación Completada!"
echo "========================================="
echo ""
echo -e "${GREEN}El sistema ha sido instalado correctamente${NC}"
echo ""
echo "Credenciales de acceso por defecto:"
echo "  Usuario: superadmin"
echo "  Contraseña: admin123"
echo ""
echo "Accede al sistema en:"
echo "  http://localhost/test-conexion.php"
echo ""
echo -e "${YELLOW}IMPORTANTE:${NC} Cambia las contraseñas por defecto"
echo ""
