<?php
/**
 * app/Services/AuditoriaService.php
 *
 * Servicio centralizado de auditoría.
 * TODO el sistema lo usa para registrar acciones críticas.
 *
 * Uso desde cualquier Controller o Service:
 *   AuditoriaService::log('productos', 'crear', $id, 'Producto creado: Tóner HP');
 */

class AuditoriaService
{
    private static ?AuditoriaModel $model = null;

    private static function model(): AuditoriaModel
    {
        if (self::$model === null) {
            self::$model = new AuditoriaModel();
        }
        return self::$model;
    }

    // ----------------------------------------------------------------
    // MÉTODO PRINCIPAL
    // ----------------------------------------------------------------

    /**
     * Registra una acción en la bitácora.
     *
     * @param string      $modulo       Módulo del sistema (productos, usuarios, vales…)
     * @param string      $accion       Acción realizada (crear, editar, eliminar, login…)
     * @param int|null    $afectadoId   ID del registro afectado
     * @param string|null $descripcion  Descripción legible
     * @param array|null  $datosAntes   Estado anterior (para ediciones)
     * @param array|null  $datosDespues Estado nuevo
     */
    public static function log(
        string  $modulo,
        string  $accion,
        ?int    $afectadoId   = null,
        ?string $descripcion  = null,
        ?array  $datosAntes   = null,
        ?array  $datosDespues = null
    ): void {
        try {
            self::model()->registrar([
                'usuario_id'    => $_SESSION['usuario_id'] ?? null,
                'modulo'        => $modulo,
                'accion'        => $accion,
                'afectado_id'   => $afectadoId,
                'descripcion'   => $descripcion,
                'datos_antes'   => $datosAntes,
                'datos_despues' => $datosDespues,
                'ip'            => Security::getClientIp(),
                'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (Throwable $e) {
            // La auditoría nunca debe romper el flujo principal.
            // Si falla, solo loggeamos el error en archivo.
            Logger::error('AUDITORIA', 'Error al registrar auditoría: ' . $e->getMessage(), [
                'modulo' => $modulo,
                'accion' => $accion,
            ]);
        }
    }

    // ----------------------------------------------------------------
    // ATAJOS SEMÁNTICOS (facilitan lectura del código)
    // ----------------------------------------------------------------

    public static function login(int $usuarioId, string $email): void
    {
        self::log('auth', 'login', $usuarioId, "Login exitoso: {$email}");
    }

    public static function logout(int $usuarioId): void
    {
        self::log('auth', 'logout', $usuarioId, 'Cierre de sesión');
    }

    public static function loginFallido(string $email, string $razon): void
    {
        self::log('auth', 'login_fallido', null, "Intento fallido: {$email} — {$razon}");
    }
}
