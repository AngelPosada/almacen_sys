<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * app/Controllers/UsuarioController.php
 *
 * Gestión de usuarios del sistema.
 * Solo accesible para Administrador (rol 1).
 *
 * Rutas:
 *   GET  /usuarios              → index()
 *   POST /usuarios/{id}/rol     → updateRol()
 *   POST /usuarios/{id}/estado  → toggleEstado()
 */

class UsuarioController extends BaseController
{
    private UsuarioModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new UsuarioModel();
    }

    // ----------------------------------------------------------------
    // GET /usuarios
    // ----------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole([1]); // Solo Admin

        $usuarios = $this->model->getAll(true); // incluir inactivos

        // Formatear para vista
        $usuarios = array_map(function (array $u): array {
            return array_merge($u, [
                'rol_nombre'     => $this->config['roles'][$u['rol_id']] ?? 'Desconocido',
                'ultimo_acceso_fmt' => $u['ultimo_acceso']
                    ? date('d/m/Y H:i', strtotime($u['ultimo_acceso']))
                    : 'Nunca',
                'creado_fmt'     => date('d/m/Y', strtotime($u['creado_en'])),
                'es_yo'          => (int) $u['id'] === (int) $_SESSION['usuario_id'],
            ]);
        }, $usuarios);

        $this->render('usuarios/index', [
            'pageTitle'  => 'Usuarios',
            'breadcrumb' => [
                ['label' => 'Administración'],
                ['label' => 'Usuarios'],
            ],
            'usuarios'   => $usuarios,
            'roles'      => $this->config['roles'],
        ]);
    }

    // ----------------------------------------------------------------
    // POST /usuarios/{id}/rol
    // ----------------------------------------------------------------

    public function updateRol(array $params): void
    {
        $this->requireAuth();
        $this->requireRole([1]);
        Security::verifyCsrf();

        $id    = (int) ($params['id'] ?? 0);
        $rolId = (int) ($this->input('rol_id', 0));

        // No puede cambiar su propio rol
        if ($id === (int) $_SESSION['usuario_id']) {
            $this->jsonError('No puedes cambiar tu propio rol.');
        }

        $rolesValidos = array_keys($this->config['roles']);
        if (!in_array($rolId, $rolesValidos, true)) {
            $this->jsonError('Rol no válido.', null, 422);
        }

        $usuario = $this->model->findById($id);
        if (!$usuario) {
            $this->jsonError('Usuario no encontrado.', null, 404);
        }

        $this->model->updateRol($id, $rolId);

        AuditoriaService::log(
            'usuarios', 'cambiar_rol', $id,
            "Rol cambiado a {$this->config['roles'][$rolId]} para {$usuario['email']}"
        );

        $this->jsonSuccess(
            "Rol actualizado a {$this->config['roles'][$rolId]}.",
            ['rol_nombre' => $this->config['roles'][$rolId]]
        );
    }

    // ----------------------------------------------------------------
    // POST /usuarios/{id}/estado
    // ----------------------------------------------------------------

    public function toggleEstado(array $params): void
    {
        $this->requireAuth();
        $this->requireRole([1]);
        Security::verifyCsrf();

        $id = (int) ($params['id'] ?? 0);

        // No puede desactivarse a sí mismo
        if ($id === (int) $_SESSION['usuario_id']) {
            $this->jsonError('No puedes desactivar tu propia cuenta.');
        }

        $usuario = $this->model->findById($id);
        if (!$usuario) {
            $this->jsonError('Usuario no encontrado.', null, 404);
        }

        $nuevoEstado = !(bool) $usuario['activo'];
        $this->model->toggleActivo($id, $nuevoEstado);

        $accion = $nuevoEstado ? 'activar' : 'desactivar';
        AuditoriaService::log(
            'usuarios', $accion, $id,
            ucfirst($accion) . " usuario: {$usuario['email']}"
        );

        $this->jsonSuccess(
            "Usuario " . ($nuevoEstado ? 'activado' : 'desactivado') . " correctamente.",
            ['activo' => $nuevoEstado]
        );
    }
}
