<?php
/**
 * app/Controllers/ReporteController.php
 *
 * Controlador de Reportes y Auditoría.
 *
 * Rutas:
 *   GET  /reportes               → index()         — resumen ejecutivo
 *   GET  /reportes/inventario    → inventario()
 *   GET  /reportes/movimientos   → movimientos()
 *   GET  /reportes/requisiciones → requisiciones()
 *   GET  /reportes/auditoria     → auditoria()     — solo Admin/Auditor
 *   POST /reportes/exportar      → exportar()      — descarga de datos
 */

class ReporteController extends BaseController
{
    private ReporteService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ReporteService();
    }

    // ----------------------------------------------------------------
    // GET /reportes
    // ----------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $datos = $this->service->getDatosResumenGeneral();

        $this->render('reportes/index', [
            'pageTitle'  => 'Reportes',
            'breadcrumb' => [['label' => 'Reportes']],
            'kpis'       => $datos['kpis'],
            'valor_total'=> $datos['valor_total'],
            'categorias' => $datos['categorias'],
        ]);
    }

    // ----------------------------------------------------------------
    // GET /reportes/inventario
    // ----------------------------------------------------------------

    public function inventario(): void
    {
        $this->requireAuth();

        $filtros = [
            'categoria_id' => $this->input('categoria_id', ''),
            'estado_stock' => $this->input('estado_stock', ''),
            'orden'        => $this->input('orden', 'nombre'),
        ];

        $datos = $this->service->getDatosInventario($filtros);

        $this->render('reportes/inventario', [
            'pageTitle'  => 'Reporte de Inventario',
            'breadcrumb' => [
                ['label' => 'Reportes', 'url' => 'reportes'],
                ['label' => 'Inventario'],
            ],
            'productos'  => $datos['productos'],
            'categorias' => $datos['categorias'],
            'kpis'       => $datos['kpis'],
            'filtros'    => $filtros,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /reportes/movimientos
    // ----------------------------------------------------------------

    public function movimientos(): void
    {
        $this->requireAuth();

        $pagina  = max(1, (int) $this->input('pagina', 1));
        $filtros = [
            'tipo'         => $this->input('tipo',        ''),
            'origen'       => $this->input('origen',      ''),
            'producto_id'  => $this->input('producto_id', ''),
            'usuario_id'   => $this->input('usuario_id',  ''),
            'fecha_desde'  => $this->input('fecha_desde', ''),
            'fecha_hasta'  => $this->input('fecha_hasta', ''),
        ];
        $perPage = 50;

        $datos = $this->service->getDatosMovimientos($filtros, $pagina, $perPage);

        // Listas para los selects de filtros
        $repModel  = new ReporteModel();
        $productos = $repModel->getProductosParaFiltro();
        $usuarios  = $repModel->getUsuariosParaFiltro();

        $this->render('reportes/movimientos', [
            'pageTitle'   => 'Reporte de Movimientos',
            'breadcrumb'  => [
                ['label' => 'Reportes', 'url' => 'reportes'],
                ['label' => 'Movimientos'],
            ],
            'movimientos' => $datos['movimientos'],
            'totales'     => $datos['totales'],
            'top_salidas' => $datos['top_salidas'],
            'grafico'     => $datos['grafico'],
            'filtros'     => $datos['filtros'],
            'productos'   => $productos,
            'usuarios'    => $usuarios,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /reportes/requisiciones
    // ----------------------------------------------------------------

    public function requisiciones(): void
    {
        $this->requireAuth();

        $filtros = [
            'fecha_desde' => $this->input('fecha_desde', ''),
            'fecha_hasta' => $this->input('fecha_hasta', ''),
        ];

        $datos = $this->service->getDatosRequisiciones($filtros);

        $this->render('reportes/requisiciones', [
            'pageTitle'  => 'Reporte de Requisiciones',
            'breadcrumb' => [
                ['label' => 'Reportes', 'url' => 'reportes'],
                ['label' => 'Requisiciones'],
            ],
            'lista'      => $datos['lista'],
            'kpis'       => $datos['kpis'],
            'filtros'    => $datos['filtros'],
        ]);
    }

    // ----------------------------------------------------------------
    // GET /reportes/auditoria
    // ----------------------------------------------------------------

    public function auditoria(): void
    {
        $this->requireAuth();
        $this->requireRole([1, 4]); // Admin y Auditor únicamente

        $pagina  = max(1, (int) $this->input('pagina', 1));
        $filtros = [
            'usuario_id'  => $this->input('usuario_id',  ''),
            'modulo'      => $this->input('modulo',       ''),
            'accion'      => $this->input('accion',       ''),
            'ip'          => $this->input('ip',           ''),
            'fecha_desde' => $this->input('fecha_desde',  ''),
            'fecha_hasta' => $this->input('fecha_hasta',  ''),
        ];
        $perPage = 50;

        $datos = $this->service->getDatosAuditoria($filtros, $pagina, $perPage);

        $this->render('reportes/auditoria', [
            'pageTitle'    => 'Bitácora de Auditoría',
            'breadcrumb'   => [
                ['label' => 'Reportes', 'url' => 'reportes'],
                ['label' => 'Auditoría'],
            ],
            'registros'    => $datos['registros'],
            'modulos'      => $datos['modulos'],
            'usuarios'     => $datos['usuarios'],
            'top_usuarios' => $datos['top_usuarios'],
            'filtros'      => $datos['filtros'],
        ]);
    }

    // ----------------------------------------------------------------
    // POST /reportes/exportar
    // ----------------------------------------------------------------

    /**
     * Descarga el reporte seleccionado.
     * Retorna JSON estructurado hasta que PhpSpreadsheet
     * se integre en la Fase de Dependencias Composer.
     */
    public function exportar(): void
    {
        $this->requireAuth();
        Security::verifyCsrf();

        $tipo    = Security::sanitize($this->input('tipo', ''));
        $filtros = [
            'categoria_id' => $this->input('categoria_id', ''),
            'estado_stock' => $this->input('estado_stock', ''),
            'fecha_desde'  => $this->input('fecha_desde',  ''),
            'fecha_hasta'  => $this->input('fecha_hasta',  ''),
        ];

        switch ($tipo) {
            case 'inventario':
                $datos = $this->service->getEstructuraExportInventario($filtros);
                AuditoriaService::log('reportes', 'exportar_inventario', null,
                    'Exportación de inventario generada'
                );
                $this->jsonSuccess('Datos de exportación listos.', $datos);
                break;

            default:
                $this->jsonError('Tipo de exportación no válido.', null, 422);
        }
    }
}
