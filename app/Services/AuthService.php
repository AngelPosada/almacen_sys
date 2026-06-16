<?php
/**
 * app/Services/AuthService.php
 *
 * Lógica de negocio de autenticación.
 * Recibe el perfil de Google y gestiona el usuario en la BD.
 *
 * Responsabilidades:
 *   - Crear usuario en el primer login
 *   - Actualizar datos de perfil en logins subsecuentes
 *   - Construir la sesión PHP con todos los datos necesarios
 *   - Verificar que el usuario esté activo
 */

class AuthService
{
    private UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    // ----------------------------------------------------------------
    // LOGIN (upsert + sesión)
    // ----------------------------------------------------------------

    /**
     * Procesa un login exitoso con Google.
     * Crea o actualiza el usuario y construye la sesión.
     *
     * @param  array $googleProfile  Perfil normalizado de GoogleOAuthService
     * @throws RuntimeException si el usuario está desactivado
     */
    public function processLogin(array $googleProfile): array
    {
        $usuario = $this->usuarioModel->findByGoogleId($googleProfile['google_id']);

        if ($usuario === false) {
            // Primer login: crear el usuario con rol Usuario (3) por defecto
            $nuevoId = $this->usuarioModel->create($googleProfile);
            $usuario = $this->usuarioModel->findById($nuevoId);

            Logger::info('AUTH', 'Nuevo usuario registrado', [
                'email' => $googleProfile['email'],
                'id'    => $nuevoId,
            ]);
        } else {
            // Login subsecuente: actualizar datos del perfil de Google
            $this->usuarioModel->updateOnLogin($usuario['id'], $googleProfile);
        }

        // Verificar que la cuenta esté activa
        if (!(bool) $usuario['activo']) {
            AuditoriaService::loginFallido($usuario['email'], 'Cuenta desactivada');
            throw new RuntimeException(
                'Tu cuenta ha sido desactivada. Contacta al administrador.'
            );
        }

        // Construir sesión
        $this->buildSession($usuario);

        // Registrar en auditoría
        AuditoriaService::login($usuario['id'], $usuario['email']);

        Logger::info('AUTH', "Login exitoso: {$usuario['email']}", ['id' => $usuario['id']]);

        return $usuario;
    }

    // ----------------------------------------------------------------
    // SESIÓN
    // ----------------------------------------------------------------

    /**
     * Construye la sesión PHP con todos los datos del usuario.
     * Esta estructura es el contrato de sesión del sistema:
     * todos los módulos la leen de aquí.
     */
    private function buildSession(array $usuario): void
    {
        $config = require ROOT_PATH . '/config/config.php';

        $_SESSION['usuario_id']     = (int)    $usuario['id'];
        $_SESSION['usuario_nombre'] = Security::sanitize($usuario['nombre']);
        $_SESSION['usuario_apellidos'] = Security::sanitize($usuario['apellidos'] ?? '');
        $_SESSION['usuario_email']  = $usuario['email'];
        $_SESSION['usuario_rol']    = (int)    $usuario['rol_id'];
        $_SESSION['usuario_rol_nombre'] = $config['roles'][$usuario['rol_id']] ?? 'Usuario';
        $_SESSION['usuario_avatar'] = $usuario['avatar_url'] ?? null;
        $_SESSION['last_activity']  = time();
        $_SESSION['login_en']       = date('Y-m-d H:i:s');
        $_SESSION['ip_login']       = Security::getClientIp();
    }

    /**
     * Destruye la sesión actual de forma segura.
     */
    public function logout(): void
    {
        if (isset($_SESSION['usuario_id'])) {
            AuditoriaService::logout($_SESSION['usuario_id']);
        }

        // Borrar todas las variables de sesión
        $_SESSION = [];

        // Invalidar cookie de sesión
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    // ----------------------------------------------------------------
    // VERIFICACIONES
    // ----------------------------------------------------------------

    /**
     * Verifica si hay una sesión activa.
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['usuario_id']);
    }

    /**
     * Verifica si el usuario actual tiene un rol específico.
     */
    public function hasRole(int|array $roles): bool
    {
        $userRole = $_SESSION['usuario_rol'] ?? 0;
        $roles    = is_array($roles) ? $roles : [$roles];
        return in_array($userRole, $roles, true);
    }

    /**
     * Retorna el ID del usuario autenticado.
     */
    public function currentUserId(): ?int
    {
        return $_SESSION['usuario_id'] ?? null;
    }
}
