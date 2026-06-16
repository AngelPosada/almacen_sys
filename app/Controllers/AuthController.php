<?php
/**
 * app/Controllers/AuthController.php
 *
 * Controlador de autenticación.
 * Solo coordina el flujo — toda la lógica está en AuthService y GoogleOAuthService.
 *
 * Rutas:
 *   GET  /auth/login           → loginForm()
 *   GET  /auth/google          → googleRedirect()
 *   GET  /auth/google/callback → googleCallback()
 *   POST /auth/logout          → logout()
 */

class AuthController extends BaseController
{
    private AuthService       $authService;
    private GoogleOAuthService $oauthService;

    public function __construct()
    {
        parent::__construct();
        $this->authService  = new AuthService();
        $this->oauthService = new GoogleOAuthService();
    }

    // ----------------------------------------------------------------
    // GET /auth/login
    // ----------------------------------------------------------------

    /**
     * Muestra la pantalla de login.
     * Si ya hay sesión activa, redirige al dashboard.
     */
    public function loginForm(): void
    {
        // Usuario ya autenticado → dashboard
        if ($this->authService->isAuthenticated()) {
            $this->redirect('dashboard');
        }

        $expired = isset($_GET['expired']) && $_GET['expired'] === '1';
        $error   = isset($_GET['error'])
            ? Security::sanitize($_GET['error'])
            : null;

        $this->render('auth/login', [
            'expired' => $expired,
            'error'   => $error,
        ], 'auth');
    }

    // ----------------------------------------------------------------
    // GET /auth/google
    // ----------------------------------------------------------------

    /**
     * Redirige al usuario a la pantalla de selección de cuenta de Google.
     */
    public function googleRedirect(): void
    {
        if ($this->authService->isAuthenticated()) {
            $this->redirect('dashboard');
        }

        $redirectUrl = $this->oauthService->buildRedirectUrl();
        header("Location: {$redirectUrl}");
        exit;
    }

    // ----------------------------------------------------------------
    // GET /auth/google/callback
    // ----------------------------------------------------------------

    /**
     * Google redirige aquí con el authorization code.
     * Intercambia el código, obtiene el perfil y crea la sesión.
     */
    public function googleCallback(): void
    {
        // Google puede enviar un error (ej: usuario canceló)
        if (isset($_GET['error'])) {
            $reason = Security::sanitize($_GET['error']);
            Logger::warning('AUTH', "OAuth cancelado: {$reason}");
            $this->redirect('auth/login?error=' . urlencode('Inicio de sesión cancelado.'));
        }

        $code  = $_GET['code']  ?? '';
        $state = $_GET['state'] ?? '';

        if (empty($code) || empty($state)) {
            $this->redirect('auth/login?error=' . urlencode('Respuesta inválida de Google.'));
        }

        try {
            // 1. Obtener perfil de Google
            $googleProfile = $this->oauthService->handleCallback($code, $state);

            // 2. Crear/actualizar usuario y construir sesión
            $this->authService->processLogin($googleProfile);

            // 3. Redirigir al dashboard
            $this->redirect('dashboard');

        } catch (RuntimeException $e) {
            // Error esperado (dominio inválido, cuenta desactivada, etc.)
            Logger::warning('AUTH', 'Login rechazado: ' . $e->getMessage());
            $this->redirect('auth/login?error=' . urlencode($e->getMessage()));

        } catch (Throwable $e) {
            // Error inesperado
            Logger::error('AUTH', 'Error inesperado en callback: ' . $e->getMessage());
            $this->redirect('auth/login?error=' . urlencode('Error interno. Intenta de nuevo.'));
        }
    }

    // ----------------------------------------------------------------
    // POST /auth/logout
    // ----------------------------------------------------------------

    /**
     * Cierra la sesión del usuario y redirige al login.
     */
    public function logout(): void
    {
        $this->authService->logout();
        $this->redirect('auth/login');
    }
}
