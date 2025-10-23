<?php
/**
 * Logout - Cierre de Sesión
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';

$auth = new AuthController();
$auth->logout();

header('Location: ' . BASE_URL . '/');
exit;
