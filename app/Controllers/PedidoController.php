<?php
/**
 * app/Controllers/PedidoController.php
 *
 * Controlador de Pedidos.
 *
 * Rutas:
 *   GET  /pedidos              → index()
 *   GET  /pedidos/nuevo        → create()
 *   POST /pedidos              → store()
 *   GET  /pedidos/{id}         → show()
 *   POST /pedidos/{id}/entregar→ entregar()
 *   POST /pedidos/{id}/cancelar→ cancelar()
 */

class PedidoController extends BaseController
{
    private PedidoService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new PedidoService();
    }

    // ----------------------------------------------------------------
    // GET /pedidos
    // ----------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $pagina  = max(1, (int) $this->input('pagina', 1));
        $filtros = [
            'estado'    => $this->input('estado',    ''),
            'prioridad' => $this->input('prioridad', ''),
            'fecha_desde'=> $this->input('fecha_desde', ''),
            'fecha_hasta'=> $this->input('fecha_hasta', ''),
        ];
        $perPage = (int) ($this->config['pagination']['per_page'] ?? 25);

        $resultado  = $this->service->getListaPaginada($filtros, $pagina, $perPage);
        $contadores = $this->service->getContadores();

        $this->render('pedidos/index', [
            'pageTitle'  => 'Pedidos',
            'breadcrumb' => [['label' => 'Pedidos']],
            'pedidos'    => $resultado['items'],
            'paginacion' => $resultado,
            'contadores' => $contadores,
            'filtros'    => $filtros,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /pedidos/nuevo
    // ----------------------------------------------------------------

    public function create(): void
    {
        $this->requireAuth();

        $datos = $this->service->getDatosFormulario();

        $this->render('pedidos/create', [
            'pageTitle'  => 'Nuevo pedido',
            'breadcrumb' => [
                ['label' => 'Pedidos', 'url' => 'pedidos'],
                ['label' => 'Nuevo pedido'],
            ],
            'productos'  => $datos['productos'],
            'empleados'  => $datos['empleados'],
        ]);
    }

    // ----------------------------------------------------------------
    // POST /pedidos
    // ----------------------------------------------------------------

    public function store(): void
    {
        $this->requireAuth();
        Security::verifyCsrf();

        $resultado = $this->service->crear($_POST);

        if ($resultado['ok']) {
            $this->jsonSuccess(
                "Pedido {$resultado['folio']} creado correctamente.",
                ['id' => $resultado['id'], 'folio' => $resultado['folio']]
            );
        } else {
            $this->jsonError(
                $resultado['errors']['general'] ?? 'Datos inválidos.',
                $resultado['errors'],
                422
            );
        }
    }

    // ----------------------------------------------------------------
    // GET /pedidos/{id}
    // ----------------------------------------------------------------

    public function show(array $params): void
    {
        $this->requireAuth();

        $id     = (int) ($params['id'] ?? 0);
        $pedido = $this->service->getDetalle($id);

        if (!$pedido) $this->abort(404, 'Pedido no encontrado.');

        $this->render('pedidos/show', [
            'pageTitle'  => $pedido['folio'],
            'breadcrumb' => [
                ['label' => 'Pedidos', 'url' => 'pedidos'],
                ['label' => $pedido['folio']],
            ],
            'pedido'     => $pedido,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /pedidos/{id}/entregar
    // ----------------------------------------------------------------

    public function entregar(array $params): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]); // Solo Admin y Almacenista
        Security::verifyCsrf();

        $id = (int) ($params['id'] ?? 0);

        // Ítems enviados como arrays: item_id[], cantidad_piezas[]
        $itemIds    = $_POST['item_id']         ?? [];
        $cantidades = $_POST['cantidad_piezas'] ?? [];

        $entregas = [];
        foreach ($itemIds as $i => $itemId) {
            $entregas[] = [
                'item_id'         => (int) $itemId,
                'cantidad_piezas' => (int) ($cantidades[$i] ?? 0),
            ];
        }

        $resultado = $this->service->procesarEntrega($id, $entregas);

        if ($resultado['ok']) {
            $label = (new PedidoService())->labelEstado($resultado['estado']);
            $this->jsonSuccess(
                "Entrega registrada. Estado: {$label}.",
                $resultado
            );
        } else {
            $this->jsonError($resultado['message']);
        }
    }

    // ----------------------------------------------------------------
    // POST /pedidos/{id}/cancelar
    // ----------------------------------------------------------------

    public function cancelar(array $params): void
    {
        $this->requireAuth();
        Security::verifyCsrf();

        $id     = (int) ($params['id'] ?? 0);
        $motivo = Security::sanitize($_POST['motivo'] ?? '');

        $resultado = $this->service->cancelar($id, $motivo);

        if ($resultado['ok']) {
            $this->jsonSuccess("Pedido {$resultado['folio']} cancelado.");
        } else {
            $this->jsonError($resultado['message']);
        }
    }
}
