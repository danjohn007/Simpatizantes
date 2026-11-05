<?php
/**
 * Manifiesto de la aplicación instalable (PWA) de captura en campo.
 * Es un PHP para que las rutas respeten la carpeta donde esté instalado el sistema.
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$pub = BASE_URL . '/public/';
echo json_encode([
    'name' => 'Captura en campo · ' . APP_NAME,
    'short_name' => 'Captura',
    'description' => 'Registro de simpatizantes en campo, con o sin conexión.',
    'lang' => 'es-MX',
    'start_url' => $pub . 'captura.php',
    'scope' => $pub,
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#f5f2ee',
    'theme_color' => '#111111',
    'icons' => [
        ['src' => $pub . 'img/app-icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $pub . 'img/app-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $pub . 'img/app-icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
