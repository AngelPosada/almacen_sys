<?php
/**
 * config/config.php
 *
 * Cargador principal de configuración.
 * Lee el archivo .env y expone toda la configuración del sistema.
 *
 * NUNCA hardcodear valores aquí. Todo viene del .env
 */

// ============================================================
// CARGADOR DE .env — protegido contra doble inclusión
// ============================================================
if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            die('[CONFIG] Archivo .env no encontrado. Copia .env.example a .env y configura tus valores.');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}

// ============================================================
// HELPER: leer variable de entorno con valor por defecto
// ============================================================
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            default            => $value,
        };
    }
}

// Cargar .env una sola vez
if (!defined('ENV_LOADED')) {
    define('ENV_LOADED', true);
    loadEnv(dirname(__DIR__) . '/.env');
}

// ============================================================
// CONFIGURACIÓN CENTRALIZADA
// ============================================================
return [

    // --- APLICACIÓN ---
    'app' => [
        'name'      => env('APP_NAME', 'Sistema de Almacén Escolar'),
        'env'       => env('APP_ENV', 'production'),
        'debug'     => env('APP_DEBUG', false),
        'url'       => env('APP_URL', 'http://localhost/almacen'),
        'timezone'  => env('APP_TIMEZONE', 'America/Mexico_City'),
    ],

    // --- BASE DE DATOS ---
    'db' => [
        'host'    => env('DB_HOST', 'localhost'),
        'port'    => env('DB_PORT', 3306),
        'name'    => env('DB_NAME', 'almacen_escolar'),
        'user'    => env('DB_USER', 'root'),
        'pass'    => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],

    // --- SESIONES ---
    'session' => [
        'name'     => env('SESSION_NAME', 'almacen_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', 7200),
        'secure'   => env('SESSION_SECURE', false),
    ],

    // --- GOOGLE OAUTH ---
    'google' => [
        'client_id'      => env('GOOGLE_CLIENT_ID'),
        'client_secret'  => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri'   => env('GOOGLE_REDIRECT_URI'),
        'allowed_domain' => env('GOOGLE_ALLOWED_DOMAIN'),
    ],

    // --- SMTP ---
    'smtp' => [
        'host'       => env('SMTP_HOST', 'smtp.gmail.com'),
        'port'       => (int) env('SMTP_PORT', 587),
        'user'       => env('SMTP_USER'),
        'pass'       => env('SMTP_PASS'),
        'from_name'  => env('SMTP_FROM_NAME', 'Almacén Escolar'),
        'from_email' => env('SMTP_FROM_EMAIL'),
    ],

    // --- WHATSAPP ---
    'whatsapp' => [
        'api_url'     => env('WHATSAPP_API_URL'),
        'api_token'   => env('WHATSAPP_API_TOKEN'),
        'from_number' => env('WHATSAPP_FROM_NUMBER'),
    ],

    // --- INSTITUCIÓN ---
    'institucion' => [
        'nombre'           => env('INST_NOMBRE', 'Institución Educativa'),
        'area'             => env('INST_AREA'),
        'director'         => env('INST_DIRECTOR'),
        'director_admin'   => env('INST_DIRECTOR_ADMIN'),
        'jefe_recursos'    => env('INST_JEFE_RECURSOS'),
        'whatsapp'         => env('INST_WHATSAPP'),
        'logo'             => env('INST_LOGO', 'assets/img/logo.png'),
    ],

    // --- SEGURIDAD ---
    'security' => [
        'csrf_secret'    => env('CSRF_SECRET', 'CAMBIAR_EN_PRODUCCION'),
        'encryption_key' => env('ENCRYPTION_KEY', 'CAMBIAR_EN_PRODUCCION'),
    ],

    // --- RUTAS ---
    'paths' => [
        'storage_temp'    => env('STORAGE_TEMP', 'storage/temp/'),
        'storage_exports' => env('STORAGE_EXPORTS', 'storage/exports/'),
        'storage_qr'      => env('STORAGE_QR', 'storage/qr/'),
        'logs'            => env('LOG_PATH', 'logs/'),
    ],

    // --- PAGINACIÓN ---
    'pagination' => [
        'per_page' => (int) env('ITEMS_PER_PAGE', 25),
    ],

    // --- ROLES DEL SISTEMA ---
    'roles' => [
        1 => 'Administrador',
        2 => 'Almacenista',
        3 => 'Usuario',
        4 => 'Auditor',
    ],

];
