<?php
/**
 * app/Controllers/ProductoController.php
 *
 * CRUD de Productos.
 *
 * Rutas:
 *   GET  /productos              → index()
 *   GET  /productos/{id}         → show()
 *   POST /productos              → store()
 *   POST /productos/{id}/editar  → update()
 *   POST /productos/{id}/eliminar→ destroy()
 *   GET  /productos/{id}/qr      → generarQr()
 */

class ProductoController extends BaseController
{
    private ProductoService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ProductoService();
    }

    // ----------------------------------------------------------------
    // GET /productos
    // ----------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $pagina   = max(1, (int) ($this->input('pagina', 1)));
        $filtros  = [
            'busqueda'    => $this->input('busqueda',     ''),
            'categoria_id'=> $this->input('categoria_id', 0),
            'estado_stock'=> $this->input('estado_stock', ''),
        ];
        $perPage  = (int) ($this->config['pagination']['per_page'] ?? 25);

        $resultado  = $this->service->getListaPaginada($filtros, $pagina, $perPage);
        $categorias = $this->service->getCategorias();

        $this->render('productos/index', [
            'pageTitle'  => 'Productos',
            'breadcrumb' => [
                ['label' => 'Inventario'],
                ['label' => 'Productos'],
            ],
            'productos'  => $resultado['items'],
            'paginacion' => $resultado,
            'categorias' => $categorias,
            'filtros'    => $filtros,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /productos/{id}
    // ----------------------------------------------------------------

    public function show(array $params): void
    {
        $this->requireAuth();

        $id       = (int) ($params['id'] ?? 0);
        $producto = $this->service->getDetalle($id);

        if (!$producto) $this->abort(404, 'Producto no encontrado.');

        $categorias = $this->service->getCategorias();

        $this->render('productos/show', [
            'pageTitle'  => e($producto['nombre']),
            'breadcrumb' => [
                ['label' => 'Inventario', 'url' => 'productos'],
                ['label' => 'Productos',  'url' => 'productos'],
                ['label' => $producto['nombre']],
            ],
            'producto'   => $producto,
            'categorias' => $categorias,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /productos
    // ----------------------------------------------------------------

    public function store(): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);
        Security::verifyCsrf();

        $resultado = $this->service->crear($_POST);

        if ($resultado['ok']) {
            $this->jsonSuccess('Producto creado correctamente.', [
                'id'  => $resultado['id'],
                'url' => $this->config['app']['url'] . '/productos/' . $resultado['id'],
            ]);
        } else {
            $this->jsonError('Datos inválidos.', $resultado['errors'], 422);
        }
    }

    // ----------------------------------------------------------------
    // POST /productos/{id}/editar
    // ----------------------------------------------------------------

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);
        Security::verifyCsrf();

        $id        = (int) ($params['id'] ?? 0);
        $resultado = $this->service->actualizar($id, $_POST);

        if ($resultado['ok']) {
            $this->jsonSuccess('Producto actualizado correctamente.');
        } else {
            $this->jsonError(
                $resultado['errors']['general'] ?? 'Datos inválidos.',
                $resultado['errors'],
                422
            );
        }
    }

    // ----------------------------------------------------------------
    // POST /productos/{id}/eliminar
    // ----------------------------------------------------------------

    public function destroy(array $params): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);
        Security::verifyCsrf();

        $id        = (int) ($params['id'] ?? 0);
        $resultado = $this->service->eliminar($id);

        if ($resultado['ok']) {
            $this->jsonSuccess('Producto eliminado correctamente.');
        } else {
            $this->jsonError($resultado['message']);
        }
    }

    // ----------------------------------------------------------------
    // GET /productos/{id}/qr
    // ----------------------------------------------------------------

    /**
     * Genera y descarga el QR del producto.
     * Retorna JSON con la URL del QR generado.
     *
     * Nota: la generación real de QR usa la librería phpqrcode o endroid/qr-code
     * (Fase G — Dependencias Composer). Por ahora retorna la estructura
     * para que la vista ya tenga el endpoint listo.
     */
    public function generarQr(array $params): void
    {
        $this->requireAuth();

        $id       = (int) ($params['id'] ?? 0);
        $producto = $this->service->getDetalle($id);

        if (!$producto) {
            $this->jsonError('Producto no encontrado.', null, 404);
        }

        // Placeholder hasta implementar Composer en Fase G
        $this->jsonSuccess('QR listo para generar.', [
            'codigo'  => $producto['codigo'],
            'nombre'  => $producto['nombre'],
            'mensaje' => 'La generación de QR se implementa en la Fase G (Composer/dependencias).',
        ]);
    }
}
