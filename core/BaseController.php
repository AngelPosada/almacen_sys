<?php
/**
 * core/BaseController.php
 * 
 * Clase base para todos los Controladores del sistema.
 * Solo coordina el flujo: recibe → delega → responde.
 * 
 * REGLA: Los controladores NO contienen lógica de negocio.
 *        No hacen consultas SQL directas.
 *        No generan HTML.
 */

abstract class BaseController
{
    protected array $config;

    public function __construct()
    {
        $this->config = require ROOT_PATH . '/config/config.php';
    }

    // ============================================================
    // RENDERIZADO DE VISTAS
    // ============================================================

    /**
     * Renderiza una vista pasándole datos.
     * 
     * @param string $view  Ruta relativa a /views (ej: 'productos/index')
     * @param array  $data  Variables disponibles en la vista
     * @param string $layout Layout a usar ('main', 'auth', 'print')
     */
    protected function render(
        string $view,
        array  $data   = [],
        string $layout = 'main'
    ): void {
        // Extraer $data como variables locales para la vista
        extract($data, EXTR_SKIP);

        $viewFile   = ROOT_PATH . "/views/{$view}.php";
        $layoutFile = ROOT_PATH . "/views/layouts/{$layout}.php";

        if (!file_exists($viewFile)) {
            $this->abort(404, "Vista no encontrada: {$view}");
        }

        // Capturar el contenido de la vista en buffer
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Inyectar en layout
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    // ============================================================
    // RESPUESTAS JSON (para AJAX)
    // ============================================================

    /**
     * Respuesta JSON de éxito.
     */
    protected function jsonSuccess(string $message = 'OK', mixed $data = null, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Respuesta JSON de error.
     */
    protected function jsonError(string $message = 'Error', mixed $errors = null, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ============================================================
    // REDIRECCIONES
    // ============================================================

    /**
     * Redirige a una URL relativa al sistema.
     */
    protected function redirect(string $path): void
    {
        $base = rtrim($this->config['app']['url'], '/');
        header("Location: {$base}/{$path}");
        exit;
    }

    /**
     * Redirige a la página anterior.
     */
    protected function redirectBack(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $this->config['app']['url'];
        header("Location: {$referer}");
        exit;
    }

    // ============================================================
    // SEGURIDAD
    // ============================================================

    /**
     * Verifica que el usuario esté autenticado.
     * Si no lo está, redirige al login.
     */
    protected function requireAuth(): void
    {
        if (!isset($_SESSION['usuario_id'])) {
            $this->redirect('auth/login');
        }
    }

    /**
     * Verifica que el usuario tenga un rol específico.
     * 
     * @param int|array $roles Rol(es) permitidos (IDs)
     */
    protected function requireRole(int|array $roles): void
    {
        $this->requireAuth();

        $userRole = $_SESSION['usuario_rol'] ?? 0;
        $roles    = is_array($roles) ? $roles : [$roles];

        if (!in_array($userRole, $roles, true)) {
            $this->abort(403, 'No tienes permisos para acceder a esta sección.');
        }
    }

    /**
     * Detiene la ejecución con un código HTTP y mensaje.
     */
    protected function abort(int $code, string $message = ''): void
    {
        http_response_code($code);
        $title = match ($code) {
            403 => 'Acceso Denegado',
            404 => 'Página No Encontrada',
            500 => 'Error Interno del Servidor',
            default => "Error {$code}",
        };
        $this->render('layouts/error', ['code' => $code, 'title' => $title, 'message' => $message]);
        exit;
    }

    // ============================================================
    // UTILIDADES
    // ============================================================

    /**
     * Retorna INPUT del request (POST/GET) limpio.
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Verifica si la petición es AJAX.
     */
    protected function isAjax(): bool
    {
        return (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        );
    }

    /**
     * Retorna el método HTTP actual.
     */
    protected function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
}
