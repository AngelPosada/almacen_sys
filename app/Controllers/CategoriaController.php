<?php
/**
 * app/Controllers/CategoriaController.php
 *
 * CRUD de Categorías.
 * La vista usa modales con AJAX — todas las escrituras responden JSON.
 *
 * Rutas:
 *   GET  /categorias              → index()
 *   POST /categorias              → store()
 *   POST /categorias/{id}/editar  → update()
 *   POST /categorias/{id}/eliminar→ destroy()
 */

class CategoriaController extends BaseController
{
    private CategoriaModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new CategoriaModel();
    }

    // ----------------------------------------------------------------
    // GET /categorias
    // ----------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $categorias = $this->model->getAll();
        $raices     = $this->model->getRaices();

        $this->render('categorias/index', [
            'pageTitle'  => 'Categorías',
            'breadcrumb' => [
                ['label' => 'Inventario'],
                ['label' => 'Categorías'],
            ],
            'categorias' => $categorias,
            'raices'     => $raices,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /categorias
    // ----------------------------------------------------------------

    public function store(): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]); // Admin y Almacenista

        Security::verifyCsrf();

        $datos = $this->validar();
        if ($datos['errors']) {
            $this->jsonError('Datos inválidos', $datos['errors'], 422);
        }

        if ($this->model->existeNombre($datos['nombre'], $datos['parent_id'])) {
            $this->jsonError('Ya existe una categoría con ese nombre en este nivel.');
        }

        $id = $this->model->create($datos);

        AuditoriaService::log('categorias', 'crear', $id,
            "Categoría creada: {$datos['nombre']}"
        );

        $this->jsonSuccess('Categoría creada correctamente.', ['id' => $id]);
    }

    // ----------------------------------------------------------------
    // POST /categorias/{id}/editar
    // ----------------------------------------------------------------

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);

        Security::verifyCsrf();

        $id = (int) ($params['id'] ?? 0);
        $categoria = $this->model->findById($id);

        if (!$categoria) {
            $this->jsonError('Categoría no encontrada.', null, 404);
        }

        $datos = $this->validar();
        if ($datos['errors']) {
            $this->jsonError('Datos inválidos', $datos['errors'], 422);
        }

        // Evitar que una categoría sea su propio padre
        if ((int) $datos['parent_id'] === $id) {
            $this->jsonError('Una categoría no puede ser su propio padre.');
        }

        if ($this->model->existeNombre($datos['nombre'], $datos['parent_id'], $id)) {
            $this->jsonError('Ya existe una categoría con ese nombre en este nivel.');
        }

        $this->model->update($id, $datos);

        AuditoriaService::log('categorias', 'editar', $id,
            "Categoría editada: {$datos['nombre']}",
            $categoria,
            $datos
        );

        $this->jsonSuccess('Categoría actualizada correctamente.');
    }

    // ----------------------------------------------------------------
    // POST /categorias/{id}/eliminar
    // ----------------------------------------------------------------

    public function destroy(array $params): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);

        Security::verifyCsrf();

        $id = (int) ($params['id'] ?? 0);
        $categoria = $this->model->findById($id);

        if (!$categoria) {
            $this->jsonError('Categoría no encontrada.', null, 404);
        }

        if ($this->model->tieneProductos($id)) {
            $this->jsonError(
                'No se puede eliminar: la categoría tiene productos asignados. ' .
                'Reasigna o elimina los productos primero.'
            );
        }

        if ($this->model->tieneHijos($id)) {
            $this->jsonError(
                'No se puede eliminar: la categoría tiene subcategorías. ' .
                'Elimina las subcategorías primero.'
            );
        }

        $this->model->softDelete($id);

        AuditoriaService::log('categorias', 'eliminar', $id,
            "Categoría eliminada: {$categoria['nombre']}"
        );

        $this->jsonSuccess('Categoría eliminada correctamente.');
    }

    // ----------------------------------------------------------------
    // VALIDACIÓN INTERNA
    // ----------------------------------------------------------------

    private function validar(): array
    {
        $errores = [];

        $nombre    = Security::sanitize($this->input('nombre', ''));
        $parentId  = $this->input('parent_id');
        $parentId  = ($parentId !== null && $parentId !== '') ? (int) $parentId : null;
        $icono     = Security::sanitize($this->input('icono', 'ti-tag'));
        $color     = Security::sanitize($this->input('color', '#0E734E'));
        $descripcion = Security::sanitize($this->input('descripcion', ''));
        $activo    = (int) $this->input('activo', 1);

        if (strlen($nombre) < 2) {
            $errores['nombre'] = 'El nombre debe tener al menos 2 caracteres.';
        }
        if (strlen($nombre) > 120) {
            $errores['nombre'] = 'El nombre no puede superar 120 caracteres.';
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#0E734E'; // fallback seguro
        }

        return [
            'nombre'      => $nombre,
            'descripcion' => $descripcion ?: null,
            'parent_id'   => $parentId,
            'icono'       => $icono,
            'color'       => $color,
            'activo'      => $activo,
            'errors'      => $errores,
        ];
    }
}
