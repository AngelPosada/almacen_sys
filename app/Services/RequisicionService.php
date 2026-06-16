<?php
/**
 * app/Services/RequisicionService.php
 *
 * Lógica de negocio de Requisiciones.
 *
 * Máquina de estados:
 *   borrador → enviada → validada → autorizada → comprada
 *                                 ↘ rechazada
 *              ↘ cancelada (desde borrador o enviada)
 *
 * REGLA CRÍTICA:
 *   La requisición NO mueve inventario.
 *   No instancia InventarioModel en ningún momento.
 */

class RequisicionService
{
    private RequisicionModel $model;

    private const TRANSICIONES = [
        'borrador'   => ['enviada', 'cancelada'],
        'enviada'    => ['validada', 'rechazada', 'cancelada'],
        'validada'   => ['autorizada', 'rechazada'],
        'autorizada' => ['comprada', 'rechazada'],
        'rechazada'  => [],
        'comprada'   => [],
        'cancelada'  => [],
    ];

    // Umbral configurable; se lee de configuracion BD en producción.
    // Por ahora se usa la constante del schema.
    private float $umbralCotizacion = 25000.0;

    public function __construct()
    {
        $this->model = new RequisicionModel();
    }

    // ----------------------------------------------------------------
    // DATOS PARA VISTAS
    // ----------------------------------------------------------------

    public function getListaPaginada(array $filtros, int $pagina, int $porPagina): array
    {
        if (($_SESSION['usuario_rol'] ?? 3) === 3) {
            $filtros['solo_propias'] = $_SESSION['usuario_id'];
        }

        $result          = $this->model->getPaginadas($filtros, $pagina, $porPagina);
        $result['items'] = array_map(fn($r) => $this->formatearResumen($r), $result['items']);
        return $result;
    }

    public function getDetalle(int $id): array|false
    {
        $req = $this->model->findById($id);
        if (!$req) return false;

        // Rol usuario: solo ve las suyas
        if (
            ($_SESSION['usuario_rol'] ?? 3) === 3 &&
            (int) $req['solicita_usuario_id'] !== (int) $_SESSION['usuario_id']
        ) return false;

        // Sobrescribir solicita_nombre con el director del plantel (BD tiene prioridad sobre .env)
        try {
            $db = Database::getInstance();
            $row = $db->query(
                "SELECT valor FROM configuracion WHERE clave = 'inst_director' LIMIT 1"
            )->fetch();
            $directorPlantel = $row['valor'] ?? '';
        } catch (Throwable $e) {
            $config = require ROOT_PATH . '/config/config.php';
            $directorPlantel = $config['institucion']['director'] ?? '';
        }
        if (!empty($directorPlantel)) {
            $req['solicita_nombre'] = $directorPlantel;
        }

        return $this->formatearDetalle($req);
    }

    public function getContadores(): array
    {
        return $this->model->contarPorEstado();
    }

    // ----------------------------------------------------------------
    // CREAR REQUISICIÓN
    // ----------------------------------------------------------------

    public function crear(array $input): array
    {
        // ── Validar cabecera ──
        $errores = [];

        $plantel          = Security::sanitize($input['plantel']          ?? '');
        $claveProg        = Security::sanitize($input['clave_programatica']?? '');
        $fechaElab        = $input['fecha_elaboracion'] ?? date('Y-m-d');
        $justificacion    = Security::sanitize($input['justificacion']    ?? '');
        $observaciones    = Security::sanitize($input['observaciones']    ?? '');

        if (strlen($plantel) < 2)
            $errores['plantel'] = 'El plantel/área es obligatorio.';
        if (empty($fechaElab) || !strtotime($fechaElab))
            $errores['fecha_elaboracion'] = 'La fecha de elaboración es obligatoria.';

        // ── Construir ítems (máx. 10 renglones como el formato oficial) ──
        $conceptos    = $input['concepto']          ?? [];
        $cantidades   = $input['cantidad']           ?? [];
        $specs        = $input['especificaciones']   ?? [];
        $precios      = $input['precio_unitario']    ?? [];

        $items  = [];
        $numRen = 0;

        foreach ($conceptos as $i => $concepto) {
            $concepto = Security::sanitize($concepto);
            if ($concepto === '') continue;

            $cantidad = (float) str_replace(',', '.', $cantidades[$i] ?? '0');
            $precio   = (float) str_replace(',', '', $precios[$i]    ?? '0');

            if ($cantidad <= 0) {
                $errores["cantidad_{$i}"] = "Cantidad inválida en renglón " . ($i + 1) . ".";
                continue;
            }

            $numRen++;
            $items[] = [
                'numero_item'     => $numRen,
                'concepto'        => $concepto,
                'cantidad'        => $cantidad,
                'especificaciones'=> Security::sanitize($specs[$i] ?? ''),
                'precio_unitario' => $precio,
                'total'           => round($cantidad * $precio, 2),
            ];

            if ($numRen >= 10) break; // Límite del formato
        }

        if (empty($items) && empty($errores['plantel'])) {
            $errores['items'] = 'Agrega al menos un concepto a la requisición.';
        }

        if (!empty($errores)) {
            return ['ok' => false, 'errors' => $errores];
        }

        try {
            $reqId = $this->model->create([
                'plantel'              => $plantel,
                'clave_programatica'   => $claveProg   ?: null,
                'fecha_elaboracion'    => $fechaElab,
                'justificacion'        => $justificacion ?: null,
                'observaciones'        => $observaciones  ?: null,
                'solicita_usuario_id'  => (int) $_SESSION['usuario_id'],
            ], $items, $this->umbralCotizacion);

            $req = $this->model->findById($reqId);

            AuditoriaService::log('requisiciones', 'crear', $reqId,
                "Requisición creada: {$req['folio']} — plantel: {$plantel}"
            );

            return [
                'ok'    => true,
                'id'    => $reqId,
                'folio' => $req['folio'] ?? '',
                'cotizaciones_requeridas' => (bool) ($req['cotizaciones_requeridas'] ?? false),
            ];

        } catch (Throwable $e) {
            Logger::error('REQUISICIONES', 'Error al crear: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['general' => 'Error interno al guardar.']];
        }
    }

    // ----------------------------------------------------------------
    // CAMBIO DE ESTADO
    // ----------------------------------------------------------------

    public function cambiarEstado(int $id, string $nuevoEstado): array
    {
        $req = $this->model->findById($id);
        if (!$req) return ['ok' => false, 'message' => 'Requisición no encontrada.'];

        if (!$this->transicionValida($req['estado'], $nuevoEstado)) {
            return ['ok' => false, 'message' =>
                "No se puede cambiar de '{$req['estado']}' a '{$nuevoEstado}'."
            ];
        }

        // Verificar permisos por estado destino
        $rolActual = (int) ($_SESSION['usuario_rol'] ?? 3);
        $permiso   = match ($nuevoEstado) {
            'enviada'    => $rolActual >= 1,       // cualquier usuario autenticado
            'validada'   => $rolActual <= 2,       // admin o almacenista
            'autorizada' => $rolActual === 1,      // solo admin
            'rechazada'  => $rolActual <= 2,
            'comprada'   => $rolActual <= 2,
            'cancelada'  => $rolActual <= 2 ||
                            (int)$req['solicita_usuario_id'] === (int)$_SESSION['usuario_id'],
            default      => false,
        };

        if (!$permiso) {
            return ['ok' => false, 'message' => 'No tienes permisos para esta acción.'];
        }

        $this->model->cambiarEstado($id, $nuevoEstado, (int) $_SESSION['usuario_id']);

        AuditoriaService::log('requisiciones', "estado_{$nuevoEstado}", $id,
            "Requisición {$req['folio']} → {$nuevoEstado}"
        );

        return ['ok' => true, 'folio' => $req['folio'], 'estado' => $nuevoEstado];
    }

    // ----------------------------------------------------------------
    // EXPORT EXCEL institucional
    // ----------------------------------------------------------------

    /**
     * Genera el array de datos para exportar a Excel.
     * La generación real del archivo .xlsx usa PhpSpreadsheet (Fase Composer).
     * Por ahora retorna los datos estructurados listos para esa capa.
     */
    public function getDatosExport(int $id): array|false
    {
        $req = $this->model->findById($id);
        if (!$req) return false;

        $config = require ROOT_PATH . '/config/config.php';
        $inst   = $config['institucion'];

        return [
            'folio'              => $req['folio'],
            'plantel'            => $req['plantel'],
            'clave_programatica' => $req['clave_programatica'] ?? '',
            'fecha_elaboracion'  => date('d/m/Y', strtotime($req['fecha_elaboracion'])),
            'items'              => $req['items'],
            'total_estimado'     => $req['total_estimado'],
            'justificacion'      => $req['justificacion'] ?? '',
            'cotizaciones_req'   => (bool) $req['cotizaciones_requeridas'],
            'solicita_nombre'    => (function() use ($inst, $req) {
                try {
                    $db = Database::getInstance();
                    $row = $db->query(
                        "SELECT valor FROM configuracion WHERE clave = 'inst_director' LIMIT 1"
                    )->fetch();
                    $v = $row['valor'] ?? '';
                    return !empty($v) ? $v : ($inst['director'] ?? $req['solicita_nombre'] ?? '');
                } catch (Throwable $e) {
                    return $inst['director'] ?? $req['solicita_nombre'] ?? '';
                }
            })(),
            'valida_nombre'      => $inst['jefe_recursos']  ?? '',
            'autoriza_nombre'    => $inst['director_admin'] ?? '',
            'inst_nombre'        => $inst['nombre']         ?? '',
            'inst_area'          => $inst['area']           ?? '',
        ];
    }

    // ----------------------------------------------------------------
    // HELPERS PRIVADOS
    // ----------------------------------------------------------------

    private function transicionValida(string $actual, string $nuevo): bool
    {
        return in_array($nuevo, self::TRANSICIONES[$actual] ?? [], true);
    }

    // ----------------------------------------------------------------
    // FORMATEO
    // ----------------------------------------------------------------

    private function formatearResumen(array $r): array
    {
        return array_merge($r, [
            'estado_label'   => $this->labelEstado($r['estado']),
            'estado_clase'   => $this->claseEstado($r['estado']),
            'total_fmt'      => '$' . number_format((float) $r['total_estimado'], 2),
            'fecha_fmt'      => date('d/m/Y', strtotime($r['fecha_elaboracion'])),
            'alerta_cotiz'   => (bool) $r['cotizaciones_requeridas'],
            'puede_enviar'   => $r['estado'] === 'borrador',
            'puede_cancelar' => in_array($r['estado'], ['borrador', 'enviada'], true),
        ]);
    }

    private function formatearDetalle(array $req): array
    {
        $req      = $this->formatearResumen($req);
        $subtotal = 0;

        $req['items'] = array_map(function (array $item) use (&$subtotal): array {
            $total    = (float) $item['total'];
            $subtotal += $total;
            return array_merge($item, [
                'cantidad_fmt' => number_format((float) $item['cantidad'], 3),
                'precio_fmt'   => '$' . number_format((float) $item['precio_unitario'], 4),
                'total_fmt'    => '$' . number_format($total, 2),
            ]);
        }, $req['items']);

        $req['subtotal_fmt']    = '$' . number_format($subtotal, 2);
        $req['total_con_iva']   = round($subtotal * 1.16, 2);
        $req['total_iva_fmt']   = '$' . number_format($req['total_con_iva'], 2);

        // Estados disponibles para transición
        $rolActual = (int) ($_SESSION['usuario_rol'] ?? 3);
        $req['acciones_disponibles'] = array_filter(
            self::TRANSICIONES[$req['estado']] ?? [],
            fn($e) => match ($e) {
                'enviada'    => true,
                'validada'   => $rolActual <= 2,
                'autorizada' => $rolActual === 1,
                'rechazada'  => $rolActual <= 2,
                'comprada'   => $rolActual <= 2,
                'cancelada'  => $rolActual <= 2 ||
                                (int)$req['solicita_usuario_id'] === (int)$_SESSION['usuario_id'],
                default      => false,
            }
        );

        return $req;
    }

    public function labelEstado(string $e): string
    {
        return match ($e) {
            'borrador'   => 'Borrador',
            'enviada'    => 'Enviada',
            'validada'   => 'Validada',
            'autorizada' => 'Autorizada',
            'rechazada'  => 'Rechazada',
            'comprada'   => 'Comprada',
            'cancelada'  => 'Cancelada',
            default      => ucfirst($e),
        };
    }

    public function claseEstado(string $e): string
    {
        return match ($e) {
            'borrador'   => 'badge-muted',
            'enviada'    => 'badge-pending',
            'validada'   => 'badge-info',
            'autorizada' => 'badge-success',
            'rechazada'  => 'badge-danger',
            'comprada'   => 'badge-success',
            'cancelada'  => 'badge-danger',
            default      => 'badge-muted',
        };
    }
}
