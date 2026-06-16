<?php
/**
 * app/Helpers/Logger.php
 * 
 * Sistema centralizado de logging.
 * Escribe logs al archivo diario en /logs/
 * 
 * Uso:
 *   Logger::info('AUTH', 'Usuario inició sesión', ['user_id' => 5]);
 *   Logger::error('DB', 'Conexión fallida', ['host' => 'localhost']);
 */

class Logger
{
    private const LEVELS = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];

    public static function debug(string $module, string $message, array $context = []): void
    {
        self::write('DEBUG', $module, $message, $context);
    }

    public static function info(string $module, string $message, array $context = []): void
    {
        self::write('INFO', $module, $message, $context);
    }

    public static function warning(string $module, string $message, array $context = []): void
    {
        self::write('WARNING', $module, $message, $context);
    }

    public static function error(string $module, string $message, array $context = []): void
    {
        self::write('ERROR', $module, $message, $context);
    }

    public static function critical(string $module, string $message, array $context = []): void
    {
        self::write('CRITICAL', $module, $message, $context);
    }

    // ============================================================
    // ESCRITURA
    // ============================================================

    private static function write(
        string $level,
        string $module,
        string $message,
        array  $context = []
    ): void {
        $logDir  = defined('ROOT_PATH') ? ROOT_PATH . '/logs/' : __DIR__ . '/../../logs/';
        $logFile = $logDir . date('Y-m-d') . '.log';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $ip         = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $user       = $_SESSION['usuario_id'] ?? '-';

        $line = sprintf(
            "[%s] [%s] [%s] [IP:%s] [USR:%s] %s%s\n",
            date('Y-m-d H:i:s'),
            str_pad($level, 8),
            strtoupper($module),
            $ip,
            $user,
            $message,
            $contextStr
        );

        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
