#!/usr/bin/env php
<?php
/**
 * cron/procesar_notificaciones.php
 *
 * Worker de notificaciones.
 * Procesa la cola de correos y WhatsApp pendientes.
 *
 * Configurar en crontab:
 *   * * * * * /usr/bin/php /ruta/al/proyecto/almacen/cron/procesar_notificaciones.php
 *
 * O en cPanel → Trabajos Cron → cada 5 minutos:
 *   /usr/local/bin/php /home/usuario/public_html/almacen/cron/procesar_notificaciones.php
 *
 * El script tiene un lock para evitar ejecuciones concurrentes.
 */

// ── Bootstrap mínimo ──
define('ROOT_PATH', dirname(__DIR__));
date_default_timezone_set('America/Mexico_City');

// Cargar autoloader de Composer
if (!file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    die("[CRON] vendor/autoload.php no encontrado. Ejecuta: composer install\n");
}
require_once ROOT_PATH . '/vendor/autoload.php';

// Cargar core del sistema
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/Database.php';
require_once ROOT_PATH . '/app/Helpers/Logger.php';
require_once ROOT_PATH . '/app/Helpers/Security.php';
require_once ROOT_PATH . '/app/Services/MailService.php';

// ── Lock anti-concurrencia ──
$lockFile = ROOT_PATH . '/storage/temp/notif_worker.lock';

if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    // Si el lock tiene más de 10 minutos, es un proceso colgado — limpiar
    if ((time() - $lockTime) < 600) {
        echo "[CRON] Ya hay un worker corriendo. Saliendo.\n";
        exit(0);
    }
}

file_put_contents($lockFile, getmypid());

// ── Procesar la cola ──
$inicio = microtime(true);

try {
    $mailService = new MailService();
    $resultado   = $mailService->procesarCola(20);

    $duracion = round(microtime(true) - $inicio, 3);
    $log = "[CRON] Notificaciones procesadas en {$duracion}s | " .
           "Enviadas: {$resultado['enviados']} | " .
           "Fallidas: {$resultado['fallidos']}";

    echo $log . "\n";
    Logger::info('CRON', $log);

} catch (Throwable $e) {
    Logger::error('CRON', 'Error en worker de notificaciones: ' . $e->getMessage());
    echo "[CRON] Error: " . $e->getMessage() . "\n";
} finally {
    // Siempre liberar el lock
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}
