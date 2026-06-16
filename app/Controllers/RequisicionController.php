<?php
/**
 * app/Controllers/RequisicionController.php
 *
 * Controlador de Requisiciones.
 *
 * Rutas:
 *   GET  /requisiciones              → index()
 *   GET  /requisiciones/nueva        → create()
 *   POST /requisiciones              → store()
 *   GET  /requisiciones/{id}         → show()
 *   POST /requisiciones/{id}/pdf     → exportPdf()
 *   POST /requisiciones/{id}/excel   → exportExcel()
 *
 * Las transiciones de estado se manejan via fetch POST con action=ESTADO.
 */

class RequisicionController extends BaseController
{
    private RequisicionService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new RequisicionService();
    }

    // ----------------------------------------------------------------
    // GET /requisiciones
    // ----------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $pagina  = max(1, (int) $this->input('pagina', 1));
        $filtros = [
            'estado'      => $this->input('estado',      ''),
            'plantel'     => $this->input('plantel',     ''),
            'fecha_desde' => $this->input('fecha_desde', ''),
            'fecha_hasta' => $this->input('fecha_hasta', ''),
        ];
        $perPage    = (int) ($this->config['pagination']['per_page'] ?? 25);
        $resultado  = $this->service->getListaPaginada($filtros, $pagina, $perPage);
        $contadores = $this->service->getContadores();

        $this->render('requisiciones/index', [
            'pageTitle'   => 'Requisiciones',
            'breadcrumb'  => [['label' => 'Requisiciones']],
            'requisiciones'=> $resultado['items'],
            'paginacion'  => $resultado,
            'contadores'  => $contadores,
            'filtros'     => $filtros,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /requisiciones/nueva
    // ----------------------------------------------------------------

    public function create(): void
    {
        $this->requireAuth();

        $this->render('requisiciones/create', [
            'pageTitle'  => 'Nueva requisición',
            'breadcrumb' => [
                ['label' => 'Requisiciones', 'url' => 'requisiciones'],
                ['label' => 'Nueva requisición'],
            ],
        ]);
    }

    // ----------------------------------------------------------------
    // POST /requisiciones
    // ----------------------------------------------------------------

    public function store(): void
    {
        $this->requireAuth();
        Security::verifyCsrf();

        $resultado = $this->service->crear($_POST);

        if ($resultado['ok']) {
            $extra = [];
            if ($resultado['cotizaciones_requeridas']) {
                $extra['alerta'] = 'El costo total supera $25,000. Se requieren 3 cotizaciones adjuntas.';
            }
            $this->jsonSuccess(
                "Requisición {$resultado['folio']} creada correctamente.",
                array_merge(['id' => $resultado['id'], 'folio' => $resultado['folio']], $extra)
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
    // GET /requisiciones/{id}
    // ----------------------------------------------------------------

    public function show(array $params): void
    {
        $this->requireAuth();

        $id  = (int) ($params['id'] ?? 0);
        $req = $this->service->getDetalle($id);

        if (!$req) $this->abort(404, 'Requisición no encontrada.');

        // Cambio de estado via AJAX (POST a este mismo endpoint con action=estado)
        if ($this->method() === 'POST' && $this->isAjax()) {
            $this->procesarCambioEstado($id);
            return;
        }

        $config = $this->config;

        $this->render('requisiciones/show', [
            'pageTitle'  => $req['folio'],
            'breadcrumb' => [
                ['label' => 'Requisiciones', 'url' => 'requisiciones'],
                ['label' => $req['folio']],
            ],
            'req'        => $req,
            'config'     => $config,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /requisiciones/{id}/pdf
    // ----------------------------------------------------------------

    public function exportPdf(array $params): void
    {
        $this->requireAuth();

        $id  = (int) ($params['id'] ?? 0);
        $req = $this->service->getDetalle($id);

        if (!$req) $this->abort(404, 'Requisición no encontrada.');

        $config = $this->config;

        // Renderizar vista de impresión (HTML → mPDF en Fase Composer)
        ob_start();
        require ROOT_PATH . '/views/requisiciones/pdf.php';
        $html = ob_get_clean();

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    // ----------------------------------------------------------------
    // POST /requisiciones/{id}/excel
    // ----------------------------------------------------------------

    /**
     * Exporta la requisición como Excel institucional.
     * Estructura de datos lista; PhpSpreadsheet se integra en Fase Composer.
     */
    public function exportExcel(array $params): void
    {
        $this->requireAuth();

        $id   = (int) ($params['id'] ?? 0);
        $data = $this->service->getDatosExport($id);

        if (!$data) $this->abort(404, 'Requisición no encontrada.');

        try {
            $excelService = new ExcelService();
            $excelService->exportarRequisicion($data);

        } catch (RuntimeException $e) {
            // PhpSpreadsheet no instalado o error — mostrar página de error clara
            http_response_code(500);
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
                  <title>Error</title></head><body style="font-family:Arial;padding:2rem">
                  <h2 style="color:#c0392b">Error al generar Excel</h2>
                  <p>' . htmlspecialchars($e->getMessage()) . '</p>
                  <p><a href="javascript:history.back()">← Volver</a></p>
                  </body></html>';
            exit;
        }
    }

    // ----------------------------------------------------------------
    // CAMBIO DE ESTADO (AJAX interno)
    // ----------------------------------------------------------------

    private function procesarCambioEstado(int $id): void
    {
        Security::verifyCsrf();

        $nuevoEstado = Security::sanitize($this->input('action', ''));
        $estadosValidos = ['enviada','validada','autorizada','rechazada','comprada','cancelada'];

        if (!in_array($nuevoEstado, $estadosValidos, true)) {
            $this->jsonError('Acción no válida.', null, 422);
        }

        $resultado = $this->service->cambiarEstado($id, $nuevoEstado);

        if ($resultado['ok']) {
            $label = (new RequisicionService())->labelEstado($resultado['estado']);
            $this->jsonSuccess("Requisición {$resultado['folio']} → {$label}.", $resultado);
        } else {
            $this->jsonError($resultado['message']);
        }
    }
}
