<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * app/Controllers/ConfiguracionController.php
 *
 * Panel de configuración institucional.
 * Solo accesible para Administrador (rol 1).
 *
 * Rutas:
 *   GET  /configuracion → index()
 *   POST /configuracion → update()
 */

class ConfiguracionController extends BaseController
{
    private ConfiguracionModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ConfiguracionModel();
    }

    // ----------------------------------------------------------------
    // GET /configuracion
    // ----------------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole([1]);

        $configuracion = $this->model->getAll();

        $this->render('configuracion/index', [
            'pageTitle'     => 'Configuración',
            'breadcrumb'    => [
                ['label' => 'Administración'],
                ['label' => 'Configuración'],
            ],
            'configuracion' => $configuracion,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /configuracion
    // ----------------------------------------------------------------

    public function update(): void
    {
        $this->requireAuth();
        $this->requireRole([1]);
        Security::verifyCsrf();

        // Claves editables desde el panel (whitelist de seguridad)
        $clavesEditables = [
            'inst_nombre',
            'inst_area',
            'inst_director',
            'inst_director_admin',
            'inst_jefe_recursos',
            'inst_whatsapp',
            'sistema_nombre',
            'cotizacion_umbral',
            'stock_alerta_email',
            'items_por_pagina',
        ];

        $datos  = [];
        $errores = [];

        foreach ($clavesEditables as $clave) {
            if (!isset($_POST[$clave])) continue;

            $valor = Security::sanitize($_POST[$clave]);

            // Validaciones por tipo
            switch ($clave) {
                case 'cotizacion_umbral':
                    $valor = (float) str_replace(',', '', $valor);
                    if ($valor <= 0) {
                        $errores[$clave] = 'El umbral debe ser mayor a cero.';
                        continue 2;
                    }
                    $valor = (string) $valor;
                    break;

                case 'items_por_pagina':
                    $valor = max(10, min(100, (int) $valor));
                    $valor = (string) $valor;
                    break;

                case 'stock_alerta_email':
                    $valor = isset($_POST[$clave]) && $_POST[$clave] === '1' ? '1' : '0';
                    break;

                default:
                    if (strlen($valor) > 300) {
                        $errores[$clave] = 'El valor es demasiado largo.';
                        continue 2;
                    }
            }

            $datos[$clave] = $valor;
        }

        if (!empty($errores)) {
            $this->jsonError('Algunos valores son inválidos.', $errores, 422);
        }

        try {
            $this->model->setMultiple($datos, (int) $_SESSION['usuario_id']);

            // Actualizar también el .env si es necesario para valores críticos
            // (inst_director_admin, inst_jefe_recursos afectan los documentos PDF)
            AuditoriaService::log(
                'configuracion', 'actualizar', null,
                'Configuración institucional actualizada: ' . implode(', ', array_keys($datos))
            );

            $this->jsonSuccess('Configuración guardada correctamente.');

        } catch (Throwable $e) {
            Logger::error('CONFIG', 'Error al guardar configuración: ' . $e->getMessage());
            $this->jsonError('Error interno al guardar. Intenta de nuevo.');
        }
    }
}
