<?php
/**
 * app/Services/PedidoService.php
 *
 * Lógica de negocio de Pedidos.
 *
 * REGLA CRÍTICA — grabada a fuego aquí:
 *   Un pedido NUNCA descuenta stock.
 *   Este servicio NO instancia InventarioModel.
 *   El stock baja ÚNICAMENTE cuando ValeService emite un vale de salida.
 *
 * Máquina de estados de un pedido:
 *   pendiente → en_proceso → entregado_parcial → entregado
 *                                              → cancelado
 *   pendiente → cancelado
 */

class PedidoService
{
    private PedidoModel   $pedidoModel;
    private ProductoModel $productoModel;
    private EmpleadoModel $empleadoModel;

    // Transiciones de estado válidas
    private const TRANSICIONES = [
        'pendiente'         => ['en_proceso', 'cancelado'],
        'en_proceso'        => ['entregado_parcial', 'entregado', 'cancelado'],
        'entregado_parcial' => ['entregado', 'cancelado'],
        'entregado'         => [],    // estado final
        'cancelado'         => [],    // estado final
    ];

    public function __construct()
    {
        $this->pedidoModel   = new PedidoModel();
        $this->productoModel = new ProductoModel();
        $this->empleadoModel = new EmpleadoModel();
    }

    // ----------------------------------------------------------------
    // DATOS PARA VISTAS
    // ----------------------------------------------------------------

    public function getListaPaginada(array $filtros, int $pagina, int $porPagina): array
    {
        // Usuarios con rol 3 solo ven sus propios pedidos
        if (($_SESSION['usuario_rol'] ?? 3) === 3) {
            $filtros['solo_propios'] = $_SESSION['usuario_id'];
        }

        $resultado           = $this->pedidoModel->getPaginados($filtros, $pagina, $porPagina);
        $resultado['items']  = array_map(
            fn($p) => $this->formatearResumen($p),
            $resultado['items']
        );
        return $resultado;
    }

    public function getDetalle(int $id): array|false
    {
        $pedido = $this->pedidoModel->findById($id);
        if (!$pedido) return false;

        // Usuarios con rol 3: solo pueden ver sus propios pedidos
        if (
            ($_SESSION['usuario_rol'] ?? 3) === 3 &&
            (int) $pedido['solicitante_id'] !== (int) $_SESSION['usuario_id']
        ) {
            return false;
        }

        return $this->formatearDetalle($pedido);
    }

    public function getDatosFormulario(): array
    {
        return [
            'productos'  => $this->productoModel->getParaSelect(),
            'empleados'  => $this->empleadoModel->getParaSelect(),
        ];
    }

    public function getContadores(): array
    {
        return $this->pedidoModel->contarPorEstado();
    }

    // ----------------------------------------------------------------
    // CREAR PEDIDO
    // ----------------------------------------------------------------

    /**
     * Valida y crea un nuevo pedido.
     *
     * El formulario envía los ítems como arrays paralelos:
     *   producto_id[]      → IDs de productos
     *   cantidad_cajas[]   → cajas por producto
     *   cantidad_piezas[]  → piezas sueltas por producto
     */
    public function crear(array $input): array
    {
        // ── Validar cabecera ──
        $errores  = [];
        $plantel  = Security::sanitize($input['plantel']       ?? '');
        $prioridad= in_array($input['prioridad'] ?? '', ['normal','urgente'])
                    ? $input['prioridad'] : 'normal';
        $obs      = Security::sanitize($input['observaciones'] ?? '');
        $empId    = !empty($input['empleado_id']) ? (int) $input['empleado_id'] : null;
        $fechaReq = !empty($input['fecha_requerida'])
                    ? $input['fecha_requerida'] : null;

        // ── Validar y construir ítems ──
        $productoIds    = $input['producto_id']    ?? [];
        $cantCajas      = $input['cantidad_cajas'] ?? [];
        $cantPiezas     = $input['cantidad_piezas'] ?? [];
        $obsItems       = $input['obs_item']        ?? [];

        if (empty($productoIds)) {
            $errores['items'] = 'Agrega al menos un producto al pedido.';
        }

        $itemsValidos = [];
        $productosVisto = [];

        foreach ($productoIds as $i => $prodId) {
            $prodId = (int) $prodId;
            if ($prodId <= 0) continue;

            // Verificar duplicados en el mismo pedido
            if (in_array($prodId, $productosVisto, true)) {
                $errores["item_{$i}"] = 'Producto duplicado en el pedido.';
                continue;
            }
            $productosVisto[] = $prodId;

            $producto = $this->productoModel->findById($prodId);
            if (!$producto) continue;

            $cajas  = max(0, (int) ($cantCajas[$i]  ?? 0));
            $piezas = max(0, (int) ($cantPiezas[$i] ?? 0));
            $total  = ProductoService::cajasAPiezas(
                $cajas, $piezas, (int) $producto['unidades_por_caja']
            );

            if ($total <= 0) {
                $errores["cant_{$i}"] = "La cantidad para {$producto['nombre']} debe ser mayor a cero.";
                continue;
            }

            $itemsValidos[] = [
                'producto_id'     => $prodId,
                'cantidad_piezas' => $total,
                'observacion'     => Security::sanitize($obsItems[$i] ?? ''),
            ];
        }

        if (empty($itemsValidos) && empty($errores['items'])) {
            $errores['items'] = 'Agrega al menos un producto con cantidad válida.';
        }

        if (!empty($errores)) {
            return ['ok' => false, 'errors' => $errores];
        }

        // ── Verificar que el empleado existe (si se indicó) ──
        if ($empId !== null && !$this->empleadoModel->findById($empId)) {
            return ['ok' => false, 'errors' => ['empleado_id' => 'Empleado no encontrado.']];
        }

        // ── Crear en BD ──
        try {
            $pedidoId = $this->pedidoModel->create([
                'solicitante_id'  => (int) $_SESSION['usuario_id'],
                'empleado_id'     => $empId,
                'plantel'         => $plantel ?: null,
                'prioridad'       => $prioridad,
                'observaciones'   => $obs    ?: null,
                'fecha_requerida' => $fechaReq,
            ], $itemsValidos);

            $pedido = $this->pedidoModel->findById($pedidoId);

            AuditoriaService::log(
                'pedidos', 'crear', $pedidoId,
                "Pedido creado: {$pedido['folio']} con " . count($itemsValidos) . " ítem(s)"
            );

            return ['ok' => true, 'id' => $pedidoId, 'folio' => $pedido['folio'] ?? ''];

        } catch (Throwable $e) {
            Logger::error('PEDIDOS', 'Error al crear pedido: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['general' => 'Error interno al guardar el pedido.']];
        }
    }

    // ----------------------------------------------------------------
    // PROCESAR ENTREGA (PARCIAL O TOTAL)
    // ----------------------------------------------------------------

    /**
     * Registra la entrega de ítems de un pedido.
     *
     * IMPORTANTE: Este método NO descuenta stock.
     * Solo actualiza estados de pedido_items y del pedido.
     * El descuento de stock ocurre cuando ValeService emite el vale.
     *
     * @param int   $pedidoId   ID del pedido
     * @param array $entregas   [{item_id, cantidad_piezas}]
     */
    public function procesarEntrega(int $pedidoId, array $entregas): array
    {
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            return ['ok' => false, 'message' => 'Pedido no encontrado.'];
        }

        // Verificar transición válida
        if (!$this->transicionValida($pedido['estado'], 'en_proceso') &&
            $pedido['estado'] !== 'en_proceso' &&
            $pedido['estado'] !== 'entregado_parcial') {
            return ['ok' => false, 'message' =>
                "No se puede procesar un pedido en estado '{$pedido['estado']}'."
            ];
        }

        if (empty($entregas)) {
            return ['ok' => false, 'message' => 'No hay ítems para entregar.'];
        }

        // Construir índice de ítems del pedido
        $itemsIndex = [];
        foreach ($pedido['items'] as $item) {
            $itemsIndex[$item['id']] = $item;
        }

        try {
            // Registrar cada entrega
            foreach ($entregas as $e) {
                $itemId   = (int) ($e['item_id'] ?? 0);
                $cantidad = (int) ($e['cantidad_piezas'] ?? 0);

                if ($cantidad <= 0 || !isset($itemsIndex[$itemId])) continue;

                $item = $itemsIndex[$itemId];
                $pendientes = $item['cantidad_piezas'] - $item['cantidad_entregada_piezas'];

                // No entregar más de lo solicitado
                $cantidadReal = min($cantidad, $pendientes);
                if ($cantidadReal <= 0) continue;

                $this->pedidoModel->registrarEntregaItem($itemId, $cantidadReal);
            }

            // Determinar nuevo estado del pedido según los ítems
            $pedidoActualizado = $this->pedidoModel->findById($pedidoId);
            $nuevoEstado       = $this->calcularEstadoPedido($pedidoActualizado['items']);

            $this->pedidoModel->actualizarEstado(
                $pedidoId,
                $nuevoEstado,
                (int) $_SESSION['usuario_id']
            );

            AuditoriaService::log(
                'pedidos', 'entregar', $pedidoId,
                "Entrega procesada: {$pedido['folio']} → estado '{$nuevoEstado}'"
            );

            return [
                'ok'     => true,
                'estado' => $nuevoEstado,
                'folio'  => $pedido['folio'],
            ];

        } catch (Throwable $e) {
            Logger::error('PEDIDOS', 'Error en entrega: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Error interno al procesar la entrega.'];
        }
    }

    // ----------------------------------------------------------------
    // CANCELAR
    // ----------------------------------------------------------------

    public function cancelar(int $pedidoId, string $motivo): array
    {
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            return ['ok' => false, 'message' => 'Pedido no encontrado.'];
        }

        if (!$this->transicionValida($pedido['estado'], 'cancelado')) {
            return ['ok' => false, 'message' =>
                "No se puede cancelar un pedido en estado '{$pedido['estado']}'."
            ];
        }

        // Solo el solicitante o Admin/Almacenista pueden cancelar
        $rol = (int) ($_SESSION['usuario_rol'] ?? 3);
        $esPropietario = (int) $pedido['solicitante_id'] === (int) $_SESSION['usuario_id'];
        if ($rol === 3 && !$esPropietario) {
            return ['ok' => false, 'message' => 'No tienes permiso para cancelar este pedido.'];
        }

        $this->pedidoModel->cancelarItemsPendientes($pedidoId);
        $this->pedidoModel->actualizarEstado($pedidoId, 'cancelado');

        AuditoriaService::log(
            'pedidos', 'cancelar', $pedidoId,
            "Pedido cancelado: {$pedido['folio']} — Motivo: {$motivo}"
        );

        return ['ok' => true, 'folio' => $pedido['folio']];
    }

    // ----------------------------------------------------------------
    // HELPERS PRIVADOS
    // ----------------------------------------------------------------

    /**
     * Determina el estado del pedido según el estado de sus ítems.
     */
    private function calcularEstadoPedido(array $items): string
    {
        $total      = count($items);
        $entregados = 0;
        $parciales  = 0;

        foreach ($items as $item) {
            if ($item['estado_item'] === 'entregado')  $entregados++;
            if ($item['estado_item'] === 'parcial')    $parciales++;
        }

        if ($entregados === $total) return 'entregado';
        if ($entregados > 0 || $parciales > 0) return 'entregado_parcial';
        return 'en_proceso';
    }

    private function transicionValida(string $estadoActual, string $estadoNuevo): bool
    {
        return in_array($estadoNuevo, self::TRANSICIONES[$estadoActual] ?? [], true);
    }

    // ----------------------------------------------------------------
    // FORMATEO
    // ----------------------------------------------------------------

    private function formatearResumen(array $p): array
    {
        $pctEntregado = $p['total_piezas_sol'] > 0
            ? round(($p['total_piezas_ent'] / $p['total_piezas_sol']) * 100)
            : 0;

        return array_merge($p, [
            'estado_label'   => $this->labelEstado($p['estado']),
            'estado_clase'   => $this->claseEstado($p['estado']),
            'prioridad_label'=> ucfirst($p['prioridad']),
            'es_urgente'     => $p['prioridad'] === 'urgente',
            'fecha_fmt'      => date('d/m/Y', strtotime($p['creado_en'])),
            'fecha_req_fmt'  => $p['fecha_requerida']
                                ? date('d/m/Y', strtotime($p['fecha_requerida']))
                                : null,
            'pct_entregado'  => $pctEntregado,
            'puede_entregar' => in_array($p['estado'], ['pendiente','en_proceso','entregado_parcial'], true),
            'puede_cancelar' => in_array($p['estado'], ['pendiente','en_proceso'], true),
        ]);
    }

    private function formatearDetalle(array $pedido): array
    {
        $pedido = $this->formatearResumen($pedido);

        $pedido['items'] = array_map(function (array $item): array {
            $upc    = (int) $item['unidades_por_caja'];
            $unidad = $item['unidad_medida'];
            $pendientes = $item['cantidad_piezas'] - $item['cantidad_entregada_piezas'];

            return array_merge($item, [
                'sol_texto'  => ProductoService::piezasATexto($item['cantidad_piezas'],   $upc, $unidad),
                'ent_texto'  => ProductoService::piezasATexto($item['cantidad_entregada_piezas'], $upc, $unidad),
                'pend_texto' => ProductoService::piezasATexto(max(0, $pendientes), $upc, $unidad),
                'stock_texto'=> ProductoService::piezasATexto((int) $item['stock_actual'], $upc, $unidad),
                'item_clase' => match ($item['estado_item']) {
                    'entregado' => 'badge-success',
                    'parcial'   => 'badge-warning',
                    'cancelado' => 'badge-danger',
                    default     => 'badge-pending',
                },
                'item_label' => match ($item['estado_item']) {
                    'entregado' => 'Entregado',
                    'parcial'   => 'Parcial',
                    'cancelado' => 'Cancelado',
                    default     => 'Pendiente',
                },
                'puede_entregar' => in_array($item['estado_item'], ['pendiente','parcial'], true)
                                    && (int) $item['stock_actual'] > 0,
            ]);
        }, $pedido['items']);

        return $pedido;
    }

    public function labelEstado(string $estado): string
    {
        return match ($estado) {
            'pendiente'         => 'Pendiente',
            'en_proceso'        => 'En proceso',
            'entregado_parcial' => 'Entrega parcial',
            'entregado'         => 'Entregado',
            'cancelado'         => 'Cancelado',
            default             => ucfirst($estado),
        };
    }

    public function claseEstado(string $estado): string
    {
        return match ($estado) {
            'pendiente'         => 'badge-pending',
            'en_proceso'        => 'badge-info',
            'entregado_parcial' => 'badge-warning',
            'entregado'         => 'badge-success',
            'cancelado'         => 'badge-danger',
            default             => 'badge-muted',
        };
    }
}
