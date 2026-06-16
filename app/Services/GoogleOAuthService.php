<?php
/**
 * app/Services/GoogleOAuthService.php
 *
 * Implementación del flujo OAuth 2.0 con Google.
 * Sin dependencias externas — usa cURL nativo de PHP.
 *
 * Flujo:
 *   1. buildRedirectUrl()  → construye la URL de autorización de Google
 *   2. handleCallback()    → intercambia el code por tokens y obtiene perfil
 *   3. Retorna el perfil del usuario para que AuthService lo procese
 */

class GoogleOAuthService
{
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const INFO_URL  = 'https://www.googleapis.com/oauth2/v3/userinfo';
    private const SCOPE     = 'openid email profile';

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private ?string $allowedDomain;

    public function __construct()
    {
        $config              = require ROOT_PATH . '/config/config.php';
        $g                   = $config['google'];
        $this->clientId      = $g['client_id']      ?? '';
        $this->clientSecret  = $g['client_secret']  ?? '';
        $this->redirectUri   = $g['redirect_uri']   ?? '';
        $this->allowedDomain = $g['allowed_domain'] ?? null;
    }

    // ----------------------------------------------------------------
    // PASO 1 — URL DE REDIRECCIÓN A GOOGLE
    // ----------------------------------------------------------------

    /**
     * Genera la URL de autorización de Google y guarda el state en sesión.
     */
    public function buildRedirectUrl(): string
    {
        // State anti-CSRF: token aleatorio guardado en sesión
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        $params = http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
            // Restringir al dominio institucional si está configurado
            'hd'            => $this->allowedDomain ?? '',
        ]);

        return self::AUTH_URL . '?' . $params;
    }

    // ----------------------------------------------------------------
    // PASO 2 — PROCESAR CALLBACK DE GOOGLE
    // ----------------------------------------------------------------

    /**
     * Procesa el callback de Google.
     * Retorna el perfil del usuario o lanza excepción.
     *
     * @throws RuntimeException con mensaje descriptivo del error
     */
    public function handleCallback(string $code, string $state): array
    {
        // Verificar state anti-CSRF
        $expectedState = $_SESSION['oauth_state'] ?? '';
        unset($_SESSION['oauth_state']);

        if (!hash_equals($expectedState, $state)) {
            AuditoriaService::loginFallido('desconocido', 'OAuth state inválido');
            throw new RuntimeException('Estado de seguridad OAuth inválido. Intenta de nuevo.');
        }

        // Intercambiar código por tokens
        $tokens = $this->exchangeCodeForTokens($code);

        if (empty($tokens['access_token'])) {
            throw new RuntimeException('No se pudo obtener el token de acceso de Google.');
        }

        // Obtener perfil del usuario
        $profile = $this->getUserProfile($tokens['access_token']);

        if (empty($profile['email'])) {
            throw new RuntimeException('Google no devolvió un email válido.');
        }

        // Verificar dominio institucional (si está configurado)
        if ($this->allowedDomain) {
            $emailDomain = substr(strrchr($profile['email'], '@'), 1);
            if ($emailDomain !== $this->allowedDomain) {
                AuditoriaService::loginFallido(
                    $profile['email'],
                    "Dominio no permitido: {$emailDomain}"
                );
                throw new RuntimeException(
                    'Solo se permiten cuentas del dominio @' . $this->allowedDomain
                );
            }
        }

        return $profile;
    }

    // ----------------------------------------------------------------
    // INTERNOS
    // ----------------------------------------------------------------

    /**
     * Intercambia el authorization code por access_token y id_token.
     */
    private function exchangeCodeForTokens(string $code): array
    {
        $payload = http_build_query([
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        return $this->post(self::TOKEN_URL, $payload);
    }

    /**
     * Obtiene el perfil del usuario con el access_token.
     * Retorna array normalizado con las claves que usa el sistema.
     */
    private function getUserProfile(string $accessToken): array
    {
        $raw = $this->get(self::INFO_URL, $accessToken);

        return [
            'google_id'  => $raw['sub']           ?? '',
            'email'      => strtolower(trim($raw['email'] ?? '')),
            'nombre'     => $raw['given_name']     ?? $raw['name'] ?? '',
            'apellidos'  => $raw['family_name']    ?? '',
            'avatar_url' => $raw['picture']        ?? null,
        ];
    }

    /**
     * HTTP POST con cURL.
     */
    private function post(string $url, string $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode >= 400) {
            Logger::error('GOOGLE_OAUTH', "POST {$url} falló", [
                'http_code' => $httpCode,
                'curl_error' => $error,
            ]);
            throw new RuntimeException('Error de comunicación con Google. Intenta de nuevo.');
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * HTTP GET con cURL (para el endpoint userinfo).
     */
    private function get(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$accessToken}"],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode >= 400) {
            Logger::error('GOOGLE_OAUTH', "GET {$url} falló", [
                'http_code' => $httpCode,
                'curl_error' => $error,
            ]);
            throw new RuntimeException('Error al obtener perfil de Google.');
        }

        return json_decode($response, true) ?? [];
    }
}
