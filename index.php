<?php
/**
 * index.php
 * 
 * Punto de entrada único del sistema.
 * Todo el tráfico pasa por aquí (via .htaccess).
 * 
 * Responsabilidades:
 *   1. Definir constantes globales
 *   2. Iniciar sesión segura
 *   3. Autocargar clases
 *   4. Cargar helpers globales
 *   5. Iniciar el router
 */

// ============================================================
// 1. CONSTANTES GLOBALES
// ============================================================
define('ROOT_PATH', __DIR__);
define('APP_START', microtime(true));

// ============================================================
// AUTOLOADER DE COMPOSER (PhpSpreadsheet, mPDF, PHPMailer, etc.)
// ============================================================
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// ============================================================
// 2. ZONA HORARIA
// ============================================================
date_default_timezone_set('America/Mexico_City');

// ============================================================
// 3. CONFIGURACIÓN DE ERRORES
// ============================================================
// Cargar .env antes de leer el modo
require_once ROOT_PATH . '/config/config.php';
$config = require ROOT_PATH . '/config/config.php';

// Hacer $config global para que esté disponible
// en todas las vistas, layouts y servicios sin necesidad de pasarla
$GLOBALS['app_config'] = $config;

if ($config['app']['debug']) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ============================================================
// 4. SESIÓN SEGURA
// ============================================================
$sessionConfig = $config['session'];

// Garantizar que el directorio de sesiones existe y es escribible
$sessionPath = ROOT_PATH . '/storage/temp/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0755, true);
}
ini_set('session.save_path', $sessionPath);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

session_set_cookie_params([
    'lifetime' => $sessionConfig['lifetime'],
    'path'     => '/',
    'domain'   => '',
    'secure'   => $sessionConfig['secure'],
    'httponly' => true,
    'samesite' => 'Lax',   // Cambiado de Strict a Lax para permitir redirect OAuth
]);

session_name($sessionConfig['name']);
session_start();

// Regenerar ID de sesión cada 30 minutos para prevenir hijacking
if (!isset($_SESSION['_last_regen']) || time() - $_SESSION['_last_regen'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['_last_regen'] = time();
}

// ============================================================
// 5. AUTOLOADER DE CLASES (PSR-4 simplificado)
// ============================================================
spl_autoload_register(function (string $class): void {
    $directories = [
        ROOT_PATH . '/core/',
        ROOT_PATH . '/config/',
        ROOT_PATH . '/app/Controllers/',
        ROOT_PATH . '/app/Models/',
        ROOT_PATH . '/app/Services/',
        ROOT_PATH . '/app/Middleware/',
        ROOT_PATH . '/app/Helpers/',
    ];

    foreach ($directories as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ============================================================
// 6. HELPERS GLOBALES SIEMPRE DISPONIBLES
// ============================================================
require_once ROOT_PATH . '/app/Helpers/Logger.php';
require_once ROOT_PATH . '/app/Helpers/Security.php';

// ============================================================
// 7. HEADERS DE SEGURIDAD
// ============================================================
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ============================================================
// 8. ROUTER
// ============================================================
$router = new Router();
require_once ROOT_PATH . '/routes/web.php';
$router->dispatch();
