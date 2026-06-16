<?php
/**
 * app/Middleware/AuthMiddleware.php
 * 
 * Middleware de autenticación.
 * Verifica que el usuario tenga sesión activa antes de acceder a rutas protegidas.
 */

class AuthMiddleware
{
    public function handle(): void
    {
        if (!isset($_SESSION['usuario_id'])) {
            $config   = require ROOT_PATH . '/config/config.php';
            $base     = rtrim($config['app']['url'], '/');
            header("Location: {$base}/auth/login");
            exit;
        }

        // Verificar que la sesión no haya expirado
        $config   = require ROOT_PATH . '/config/config.php';
        $lifetime = $config['session']['lifetime'];

        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $lifetime) {
            session_unset();
            session_destroy();

            $base = rtrim($config['app']['url'], '/');
            header("Location: {$base}/auth/login?expired=1");
            exit;
        }

        $_SESSION['last_activity'] = time();
    }
}
