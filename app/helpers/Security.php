<?php
/**
 * app/Helpers/Security.php
 * 
 * Utilidades de seguridad del sistema.
 * 
 * Incluye:
 *   - CSRF token generation & validation
 *   - XSS prevention (htmlspecialchars wrapper)
 *   - Input sanitization
 *   - IP detection
 */

class Security
{
    // ============================================================
    // CSRF
    // ============================================================

    /**
     * Genera un token CSRF y lo almacena en sesión.
     * Retorna el token para incluir en formularios.
     */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Retorna el campo hidden HTML con el token CSRF.
     * Usar en todos los formularios: <?= Security::csrfField() ?>
     */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . self::csrfToken() . '">';
    }

    /**
     * Valida el token CSRF del request actual.
     * Mata la ejecución si el token es inválido.
     */
    public static function verifyCsrf(): void
    {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            Logger::warning('CSRF', 'Token inválido detectado', [
                'ip'  => self::getClientIp(),
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            http_response_code(403);
            die(json_encode([
                'success' => false,
                'message' => 'Token de seguridad inválido. Recarga la página.'
            ]));
        }
    }

    // ============================================================
    // XSS / SANITIZACIÓN
    // ============================================================

    /**
     * Escapa output para prevenir XSS.
     * Usar siempre al mostrar datos en HTML.
     * 
     * Alias: e($value)
     */
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Limpia un string de tags HTML y caracteres peligrosos.
     * Usar en inputs antes de guardar en BD.
     */
    public static function sanitize(string $input): string
    {
        return strip_tags(trim($input));
    }

    /**
     * Valida y sanitiza un entero.
     */
    public static function sanitizeInt(mixed $input): int
    {
        return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Valida formato de correo electrónico.
     */
    public static function isValidEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    // ============================================================
    // IP / AGENTE
    // ============================================================

    /**
     * Obtiene la IP real del cliente (considera proxies).
     */
    public static function getClientIp(): string
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}

// ============================================================
// FUNCIÓN GLOBAL HELPER (shorthand para escape en vistas)
// ============================================================
if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return Security::escape($value);
    }
}
