<?php
/**
 * app/Controllers/ValeController.php
 *
 * Controlador de Vales de Salida y Resguardo.
 *
 * Rutas:
 *   GET  /vales                  → index()
 *   GET  /vales/salida/nuevo     → createSalida()
 *   POST /vales/salida           → storeSalida()
 *   GET  /vales/resguardo/nuevo  → createResguardo()
 *   POST /vales/resguardo        → storeResguardo()
 *   GET  /vales/{id}/pdf         → exportPdf()
 *   POST /vales/{id}/enviar      → enviar()  — emite el vale
 */

class ValeController extends BaseController
{
    private ValeService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ValeService();
    }

    // ----------------------------------------------------------------
    // GET /vales
    // ----------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $pagina  = max(1, (int) $this->input('pagina', 1));
        $filtros = [
            'tipo'        => $this->input('tipo',        ''),
            'estado'      => $this->input('estado',      ''),
            'fecha_desde' => $this->input('fecha_desde', ''),
            'fecha_hasta' => $this->input('fecha_hasta', ''),
        ];
        $perPage = (int) ($this->config['pagination']['per_page'] ?? 25);

        $resultado = $this->service->getListaPaginada($filtros, $pagina, $perPage);

        $this->render('vales/index', [
            'pageTitle'  => 'Vales',
            'breadcrumb' => [['label' => 'Vales']],
            'vales'      => $resultado['items'],
            'paginacion' => $resultado,
            'filtros'    => $filtros,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /vales/salida/nuevo
    // ----------------------------------------------------------------

    public function createSalida(): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);

        $datos = $this->service->getDatosFormulario();

        $this->render('vales/create_salida', [
            'pageTitle'  => 'Nuevo vale de salida',
            'breadcrumb' => [
                ['label' => 'Vales', 'url' => 'vales'],
                ['label' => 'Nuevo vale de salida'],
            ],
            'productos'  => $datos['productos'],
            'empleados'  => $datos['empleados'],
        ]);
    }

    // ----------------------------------------------------------------
    // POST /vales/salida
    // ----------------------------------------------------------------

    public function storeSalida(): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);
        Security::verifyCsrf();

        $resultado = $this->service->crear($_POST, 'salida');

        if ($resultado['ok']) {
            $this->jsonSuccess(
                "Vale de salida {$resultado['folio']} creado.",
                ['id' => $resultado['id'], 'folio' => $resultado['folio']]
            );
        } else {
            $this->jsonError(
                $resultado['errors']['general']
                    ?? (is_array($resultado['errors']['stock'] ?? null)
                        ? implode(' | ', $resultado['errors']['stock'])
                        : 'Datos inválidos.'),
                $resultado['errors'],
                422
            );
        }
    }

    // ----------------------------------------------------------------
    // GET /vales/resguardo/nuevo
    // ----------------------------------------------------------------

    public function createResguardo(): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);

        $datos = $this->service->getDatosFormulario();

        $this->render('vales/create_resguardo', [
            'pageTitle'  => 'Nuevo vale de resguardo',
            'breadcrumb' => [
                ['label' => 'Vales', 'url' => 'vales'],
                ['label' => 'Nuevo vale de resguardo'],
            ],
            'productos'  => $datos['productos'],
            'empleados'  => $datos['empleados'],
        ]);
    }

    // ----------------------------------------------------------------
    // POST /vales/resguardo
    // ----------------------------------------------------------------

    public function storeResguardo(): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);
        Security::verifyCsrf();

        $resultado = $this->service->crear($_POST, 'resguardo');

        if ($resultado['ok']) {
            $this->jsonSuccess(
                "Vale de resguardo {$resultado['folio']} creado.",
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
    // POST /vales/{id}/enviar  — emite el vale y descuenta stock
    // ----------------------------------------------------------------

    public function enviar(array $params): void
    {
        $this->requireAuth();
        $this->requireRole([1, 2]);
        Security::verifyCsrf();

        $id            = (int) ($params['id'] ?? 0);
        $recibioNombre = Security::sanitize($_POST['recibio_nombre'] ?? '');

        $resultado = $this->service->emitir($id, $recibioNombre);

        if ($resultado['ok']) {
            $msg = $resultado['tipo'] === 'salida'
                ? "Vale {$resultado['folio']} emitido. Stock descontado correctamente."
                : "Vale de resguardo {$resultado['folio']} emitido.";
            $this->jsonSuccess($msg, $resultado);
        } else {
            $this->jsonError($resultado['message']);
        }
    }

    // ----------------------------------------------------------------
    // GET /vales/{id}/pdf
    // ----------------------------------------------------------------

    /**
     * Genera y muestra el PDF del vale en formato institucional.
     * Fiel al formato FOR 8.4 DeRM v4 (Versión 4, 13/07/2017).
     */
    public function exportPdf(array $params): void
    {
        $this->requireAuth();

        $id   = (int) ($params['id'] ?? 0);
        $vale = $this->service->getDetalle($id);

        if (!$vale) $this->abort(404, 'Vale no encontrado.');

        // Renderizar la vista de impresión (sin layout principal)
        $config = $this->config;
        ob_start();
        require ROOT_PATH . '/views/vales/pdf.php';
        $html = ob_get_clean();

        // Por ahora se entrega como HTML imprimible.
        // La generación PDF con mPDF se implementa en la Fase de Dependencias Composer.
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }
}
