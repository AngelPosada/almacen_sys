<?php
/**
 * core/Router.php
 * 
 * Sistema de enrutamiento del sistema.
 * Mapea URLs a Controllers y métodos.
 * 
 * Soporta:
 *   - Rutas estáticas:    GET /productos
 *   - Rutas con parámetro: GET /productos/{id}
 *   - Grupos de rutas con prefijo
 *   - Middleware por ruta
 */

class Router
{
    private array $routes = [];

    // ============================================================
    // REGISTRO DE RUTAS
    // ============================================================

    public function get(string $uri, string $action, array $middleware = []): self
    {
        return $this->addRoute('GET', $uri, $action, $middleware);
    }

    public function post(string $uri, string $action, array $middleware = []): self
    {
        return $this->addRoute('POST', $uri, $action, $middleware);
    }

    public function put(string $uri, string $action, array $middleware = []): self
    {
        return $this->addRoute('PUT', $uri, $action, $middleware);
    }

    public function delete(string $uri, string $action, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $uri, $action, $middleware);
    }

    private function addRoute(
        string $method,
        string $uri,
        string $action,
        array  $middleware
    ): self {
        // Convertir {param} → named capture groups para regex
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $uri);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method'     => $method,
            'uri'        => $uri,
            'pattern'    => $pattern,
            'action'     => $action,    // 'ControllerName@method'
            'middleware' => $middleware,
        ];

        return $this;
    }

    // ============================================================
    // DISPATCH
    // ============================================================

    /**
     * Resuelve la ruta actual y ejecuta el controlador.
     */
    public function dispatch(): void
    {
        $method  = $_SERVER['REQUEST_METHOD'];
        $uri     = $this->parseUri();

        // Soporte para _method override (PUT, DELETE desde forms HTML)
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            // Parámetros de la URL (ej: {id})
            $params = array_filter(
                $matches,
                fn($key) => !is_int($key),
                ARRAY_FILTER_USE_KEY
            );

            // Ejecutar middleware
            foreach ($route['middleware'] as $middlewareClass) {
                $mw = new $middlewareClass();
                $mw->handle();
            }

            // Ejecutar controlador
            $this->callAction($route['action'], $params);
            return;
        }

        // 404
        http_response_code(404);
        require ROOT_PATH . '/views/layouts/error.php';
    }

    /**
     * Instancia el controller y llama el método.
     * 
     * @param string $action  Formato: 'ControllerName@methodName'
     * @param array  $params  Parámetros capturados de la URL
     */
    private function callAction(string $action, array $params): void
    {
        [$controllerName, $method] = explode('@', $action);

        $controllerFile = ROOT_PATH . "/app/Controllers/{$controllerName}.php";

        if (!file_exists($controllerFile)) {
            http_response_code(500);
            die("[Router] Controller no encontrado: {$controllerName}");
        }

        require_once $controllerFile;

        if (!class_exists($controllerName)) {
            http_response_code(500);
            die("[Router] Clase no encontrada: {$controllerName}");
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $method)) {
            http_response_code(500);
            die("[Router] Método no encontrado: {$controllerName}@{$method}");
        }

        $controller->$method($params);
    }

    // ============================================================
    // UTILIDADES
    // ============================================================

    /**
     * Obtiene la URI limpia sin el base path del proyecto.
     */
    private function parseUri(): string
    {
        $uri      = $_SERVER['REQUEST_URI'] ?? '/';
        $basePath = parse_url(env('APP_URL', ''), PHP_URL_PATH) ?? '';

        // Remover query string
        if (str_contains($uri, '?')) {
            $uri = strstr($uri, '?', true);
        }

        // Remover el base path
        if ($basePath && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        return '/' . trim($uri, '/');
    }
}
