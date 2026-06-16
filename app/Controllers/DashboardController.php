<?php
/**
 * app/Controllers/DashboardController.php
 *
 * Controlador del Dashboard principal.
 *
 * Rutas:
 *   GET /          → index() (redirige a /dashboard)
 *   GET /dashboard → index()
 *   GET /dashboard/stats → stats() [AJAX]
 */

class DashboardController extends BaseController
{
    private DashboardService $dashboardService;

    public function __construct()
    {
        parent::__construct();
        $this->dashboardService = new DashboardService();
    }

    // ----------------------------------------------------------------
    // GET /dashboard
    // ----------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $data = $this->dashboardService->getDashboardData();

        $this->render('dashboard/index', [
            'pageTitle'  => 'Dashboard',
            'breadcrumb' => [['label' => 'Dashboard']],
            'kpis'       => $data['kpis'],
            'movimientos'=> $data['ultimos_movimientos'],
            'stock_bajo' => $data['stock_bajo'],
            'pedidos'    => $data['ultimos_pedidos'],
            'grafico'    => $data['grafico_actividad'],
        ]);
    }

    // ----------------------------------------------------------------
    // GET /dashboard/stats  [AJAX — refresca KPIs sin recargar página]
    // ----------------------------------------------------------------

    public function stats(): void
    {
        $this->requireAuth();

        if (!$this->isAjax()) {
            $this->redirect('dashboard');
        }

        $this->jsonSuccess('OK', $this->dashboardService->getStats());
    }
}
