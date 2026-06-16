<?php
/**
 * app/Controllers/InventarioController.php
 *
 * Controlador de Inventario.
 *
 * Rutas:
 *   GET  /inventario              → index()   — historial completo
 *   GET  /inventario/entradas     → entradas() — formulario de entrada
 *   POST /inventario/entradas     → registrarEntrada()
 *   GET  /inventario/salidas      → salidas()  — formulario de salida
 *   POST /inventario/salidas      → registrarSalida()
 */

class InventarioController extends BaseController
{
    private InventarioService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new InventarioService();
    }

    // ----------------------------------------------------------------
    // GET /inventario
    // ----------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $pagina  = max(1, (int) $this->input('pagina', 1));
        $filtros = [
            'producto_id' => $this->input('producto_id', ''),
            'tipo'        => $this->input('tipo', ''),
            'origen'      => $this->input('origen', ''),
            'fecha_desde' => $this->input('fecha_desde', ''),
            'fecha_hasta' => $this->input('fecha_hasta', ''),
        ];
        $perPage = (int) ($this->config['pagination']['per_page'] ?? 25);

        $datos    = $this->service->getDatosIndex();
        $historial= $this->service->getMovimientosPaginados($filtros, $pagina, $perPage);

        $this->render('inventario/index', [
            'pageTitle'   => 'Inventario',
            'breadcrumb'  => [
                ['label' => 'Inventario'],
                ['label' => 'Movimientos'],
            ],
            'totales'     => $datos['totales'],
            'movimientos' => $historial['items'],
            'paginacion'  => $historial,
            'filtros'     => $filtros,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /inventario/entradas
    // ----------------------------------------------------------------

    public function entradas(): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);

        $datos = $this->service->getDatosEntradas();

        // Si viene ?producto_id= desde otro módulo, preseleccionar
        $productoPresel = (int) $this->input('producto_id', 0);

        $this->render('inventario/entradas', [
            'pageTitle'       => 'Registrar entrada',
            'breadcrumb'      => [
                ['label' => 'Inventario', 'url' => 'inventario'],
                ['label' => 'Registrar entrada'],
            ],
            'productos'       => $datos['productos'],
            'recientes'       => $datos['recientes'],
            'producto_presel' => $productoPresel,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /inventario/entradas
    // ----------------------------------------------------------------

    public function registrarEntrada(): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);
        Security::verifyCsrf();

        $resultado = $this->service->registrarEntrada($_POST);

        if ($resultado['ok']) {
            $d = $resultado['data'];
            $this->jsonSuccess(
                "Entrada registrada: +{$d['piezas_base']} pz. de {$d['producto_nombre']}.",
                $d
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
    // GET /inventario/salidas
    // ----------------------------------------------------------------

    public function salidas(): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);

        $datos = $this->service->getDatosSalidas();

        $this->render('inventario/salidas', [
            'pageTitle'  => 'Registrar salida',
            'breadcrumb' => [
                ['label' => 'Inventario', 'url' => 'inventario'],
                ['label' => 'Registrar salida'],
            ],
            'productos'  => $datos['productos'],
            'recientes'  => $datos['recientes'],
        ]);
    }

    // ----------------------------------------------------------------
    // POST /inventario/salidas
    // ----------------------------------------------------------------

    public function registrarSalida(): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);
        Security::verifyCsrf();

        $resultado = $this->service->registrarSalida($_POST);

        if ($resultado['ok']) {
            $d = $resultado['data'];
            $this->jsonSuccess(
                "Salida registrada: −{$d['piezas_base']} pz. de {$d['producto_nombre']}.",
                $d
            );
        } else {
            $this->jsonError(
                $resultado['errors']['general'] ?? 'Datos inválidos.',
                $resultado['errors'],
                422
            );
        }
    }
}
