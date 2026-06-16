<?php
/**
 * app/Middleware/RoleMiddleware.php
 *
 * Middleware de control de acceso por roles.
 * Se configura con los roles permitidos al registrar la ruta.
 *
 * Uso en routes/web.php:
 *   $router->get('/usuarios', 'UsuarioController@index', [
 *       AuthMiddleware::class,
 *       new RoleMiddleware([1]) // Solo Admin
 *   ]);
 *
 * Roles del sistema:
 *   1 = Administrador
 *   2 = Almacenista
 *   3 = Usuario
 *   4 = Auditor
 */

class RoleMiddleware
{
    private array $rolesPermitidos;

    public function __construct(array $rolesPermitidos)
    {
        $this->rolesPermitidos = $rolesPermitidos;
    }

    public function handle(): void
    {
        $rolActual = $_SESSION['usuario_rol'] ?? 0;

        if (!in_array($rolActual, $this->rolesPermitidos, true)) {
            AuditoriaService::log(
                'auth',
                'acceso_denegado',
                $_SESSION['usuario_id'] ?? null,
                'Intento de acceso sin permisos: ' . ($_SERVER['REQUEST_URI'] ?? '')
            );

            // Si es AJAX, responder con JSON
            if (
                isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            ) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permisos para esta acción.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Si es petición normal, mostrar vista de error 403
            http_response_code(403);
            require ROOT_PATH . '/views/layouts/error.php';
            exit;
        }
    }
}
