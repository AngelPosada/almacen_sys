<?php
/**
 * app/Services/ValeService.php
 *
 * Lógica de negocio de Vales de Salida y Resguardo.
 *
 * OPERACIÓN MÁS CRÍTICA DEL SISTEMA — emitir():
 *   Única operación del sistema que descuenta stock.
 *   Triple protección:
 *     1. stock_descontado flag en BD
 *     2. SELECT FOR UPDATE en vales
 *     3. SELECT FOR UPDATE en stock (via InventarioModel)
 */

class ValeService
{
    private ValeModel       $valeModel;
    private ProductoModel   $productoModel;
    private EmpleadoModel   $empleadoModel;
    private InventarioModel $inventarioModel;

    public function __construct()
    {
        $this->valeModel       = new ValeModel();
        $this->productoModel   = new ProductoModel();
        $this->empleadoModel   = new EmpleadoModel();
        $this->inventarioModel = new InventarioModel();
    }

    // ----------------------------------------------------------------
    // DATOS PARA VISTAS
    // ----------------------------------------------------------------

    public function getListaPaginada(array $filtros, int $pagina, int $perPage): array
    {
        $resultado          = $this->valeModel->getPaginados($filtros, $pagina, $perPage);
        $resultado['items'] = array_map(fn($v) => $this->formatearResumen($v), $resultado['items']);
        return $resultado;
    }

    public function getDetalle(int $id): array|false
    {
        $vale = $this->valeModel->findById($id);
        if (!$vale) return false;
        return $this->formatearDetalle($vale);
    }

    public function getDatosFormulario(): array
    {
        return [
            'productos' => $this->productoModel->getParaSelect(),
            'empleados' => $this->empleadoModel->getParaSelect(),
        ];
    }

    // ----------------------------------------------------------------
    // CREAR VALE (borrador — no descuenta stock)
    // ----------------------------------------------------------------

    public function crear(array $input, string $tipo): array
    {
        $validado = $this->validarCabecera($input, $tipo);
        if (!empty($validado['errors'])) {
            return ['ok' => false, 'errors' => $validado['errors']];
        }

        $items = $this->construirItems($input);
        if (isset($items['error'])) {
            return ['ok' => false, 'errors' => ['items' => $items['error']]];
        }

        if ($tipo === 'salida') {
            $def = $this->verificarStockDisponible($items);
            if (!empty($def)) {
                return ['ok' => false, 'errors' => ['stock' => $def]];
            }
        }

        try {
            $valeId = $this->valeModel->create([
                'tipo'           => $tipo,
                'referencia'     => $validado['referencia'],
                'plantel'        => $validado['plantel'],
                'empleado_id'    => $validado['empleado_id'],
                'pedido_id'      => $validado['pedido_id'],
                'requisicion_id' => $validado['requisicion_id'],
                'autorizo_id'    => (int) $_SESSION['usuario_id'],
                'fecha_emision'  => $validado['fecha_emision'],
                'observaciones'  => $validado['observaciones'],
            ], $items);

            $vale = $this->valeModel->findById($valeId);

            AuditoriaService::log('vales', 'crear', $valeId,
                "Vale {$tipo} creado: {$vale['folio']} (" . count($items) . " ítems)"
            );

            return ['ok' => true, 'id' => $valeId, 'folio' => $vale['folio'] ?? ''];

        } catch (Throwable $e) {
            Logger::error('VALES', 'Error al crear vale: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['general' => 'Error interno al guardar el vale.']];
        }
    }

    // ----------------------------------------------------------------
    // EMITIR VALE — operación atómica de descuento de stock
    // ----------------------------------------------------------------

    /**
     * Emite el vale y descuenta stock en una única transacción.
     * Solo vales de SALIDA descuentan stock.
     */
    public function emitir(int $valeId, string $recibioNombre = ''): array
    {
        $vale = $this->valeModel->findById($valeId);
        if (!$vale)                          return ['ok' => false, 'message' => 'Vale no encontrado.'];
        if ($vale['estado'] !== 'borrador')  return ['ok' => false, 'message' => "Vale ya en estado '{$vale['estado']}'."];
        if (empty($vale['items']))           return ['ok' => false, 'message' => 'El vale no tiene artículos.'];

        // Transacción padre que engloba vales + inventario
        $this->inventarioModel->beginTransaction();

        try {
            // 1. Bloquear la fila del vale
            $bloqueado = $this->valeModel->leerParaDescuento($valeId);
            if (!$bloqueado) throw new RuntimeException('Vale no encontrado en transacción.');

            // 2. Guard anti-doble-descuento
            if ((int) $bloqueado['stock_descontado'] === 1) {
                throw new RuntimeException('Este vale ya fue emitido. No se puede emitir dos veces.');
            }

            // 3. Descontar stock (solo salida)
            if ($vale['tipo'] === 'salida') {
                foreach ($vale['items'] as $item) {
                    $this->inventarioModel->moverStock(
                        productoId:     (int) $item['producto_id'],
                        cantidadPiezas: -(int) $item['cantidad_piezas'],
                        tipo:           'salida',
                        origen:         'vale_salida',
                        usuarioId:      (int) $_SESSION['usuario_id'],
                        observacion:    "Vale {$vale['folio']}",
                        refTipo:        'vales',
                        refId:          $valeId
                    );
                }
            }

            // 4. Marcar emitido + stock_descontado = 1
            $this->valeModel->marcarEmitido($valeId, $recibioNombre ?: null);

            $this->inventarioModel->commit();

            AuditoriaService::log('vales', 'emitir', $valeId,
                "Vale emitido: {$vale['folio']}" .
                ($vale['tipo'] === 'salida' ? ' — stock descontado' : ' — resguardo sin descuento')
            );

            return ['ok' => true, 'folio' => $vale['folio'], 'tipo' => $vale['tipo']];

        } catch (RuntimeException $e) {
            $this->inventarioModel->rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $this->inventarioModel->rollback();
            Logger::error('VALES', 'Error crítico al emitir: ' . $e->getMessage(), ['vale_id' => $valeId]);
            return ['ok' => false, 'message' => 'Error interno al emitir el vale.'];
        }
    }

    // ----------------------------------------------------------------
    // CANCELAR
    // ----------------------------------------------------------------

    public function cancelar(int $valeId): array
    {
        $vale = $this->valeModel->findById($valeId);
        if (!$vale) return ['ok' => false, 'message' => 'Vale no encontrado.'];

        if ((int) $vale['stock_descontado'] === 1) {
            return ['ok' => false, 'message' =>
                'No se puede cancelar: el stock ya fue descontado. ' .
                'Registra una entrada manual para revertirlo.'
            ];
        }

        $filas = $this->valeModel->cancelar($valeId);
        if ($filas === 0) return ['ok' => false, 'message' => 'No se pudo cancelar el vale.'];

        AuditoriaService::log('vales', 'cancelar', $valeId, "Vale cancelado: {$vale['folio']}");
        return ['ok' => true, 'folio' => $vale['folio']];
    }

    // ----------------------------------------------------------------
    // VALIDACIONES PRIVADAS
    // ----------------------------------------------------------------

    private function validarCabecera(array $input, string $tipo): array
    {
        $errores       = [];
        $referencia    = Security::sanitize($input['referencia']    ?? '');
        $plantel       = Security::sanitize($input['plantel']       ?? '');
        $observaciones = Security::sanitize($input['observaciones'] ?? '');
        $empleadoId    = !empty($input['empleado_id'])    ? (int)$input['empleado_id']    : null;
        $pedidoId      = !empty($input['pedido_id'])      ? (int)$input['pedido_id']      : null;
        $requisicionId = !empty($input['requisicion_id']) ? (int)$input['requisicion_id'] : null;
        $fechaEmision  = $input['fecha_emision'] ?? date('Y-m-d');

        if ($tipo === 'salida' && strlen($referencia) < 3)
            $errores['referencia'] = 'La referencia es obligatoria (mín. 3 caracteres).';
        if (strlen($plantel) < 2)
            $errores['plantel'] = 'El plantel / área es obligatorio.';

        return [
            'referencia'     => $referencia,
            'plantel'        => $plantel,
            'observaciones'  => $observaciones,
            'empleado_id'    => $empleadoId,
            'pedido_id'      => $pedidoId,
            'requisicion_id' => $requisicionId,
            'fecha_emision'  => $fechaEmision,
            'errors'         => $errores,
        ];
    }

    private function construirItems(array $input): array
    {
        $prodIds   = $input['producto_id']     ?? [];
        $cajas     = $input['cantidad_cajas']  ?? [];
        $piezas    = $input['cantidad_piezas'] ?? [];
        $descs     = $input['descripcion_item']?? [];

        if (empty($prodIds)) return ['error' => 'Agrega al menos un artículo.'];

        $items  = [];
        $vistos = [];

        foreach ($prodIds as $i => $prodId) {
            $prodId = (int) $prodId;
            if ($prodId <= 0 || in_array($prodId, $vistos, true)) continue;
            $vistos[] = $prodId;

            $producto = $this->productoModel->findById($prodId);
            if (!$producto) continue;

            $total = ProductoService::cajasAPiezas(
                max(0, (int)($cajas[$i]  ?? 0)),
                max(0, (int)($piezas[$i] ?? 0)),
                (int) $producto['unidades_por_caja']
            );
            if ($total <= 0) continue;

            $items[] = [
                'producto_id'      => $prodId,
                'cantidad_piezas'  => $total,
                'precio_unitario'  => (float) $producto['precio_unitario'],
                'descripcion_item' => Security::sanitize($descs[$i] ?? $producto['nombre']),
            ];
        }

        return empty($items) ? ['error' => 'Ingresa cantidades mayores a cero.'] : $items;
    }

    private function verificarStockDisponible(array $items): array
    {
        $def = [];
        foreach ($items as $item) {
            $stock    = $this->inventarioModel->getStock($item['producto_id']);
            $producto = $this->productoModel->findById($item['producto_id']);
            if ($stock < $item['cantidad_piezas']) {
                $disp = ProductoService::piezasATexto($stock, (int)$producto['unidades_por_caja'], $producto['unidad_medida']);
                $sol  = ProductoService::piezasATexto($item['cantidad_piezas'], (int)$producto['unidades_por_caja'], $producto['unidad_medida']);
                $def[] = "{$producto['nombre']}: solicitado {$sol}, disponible {$disp}.";
            }
        }
        return $def;
    }

    // ----------------------------------------------------------------
    // FORMATEO
    // ----------------------------------------------------------------

    private function formatearResumen(array $v): array
    {
        return array_merge($v, [
            'tipo_label'    => $v['tipo'] === 'salida' ? 'Salida' : 'Resguardo',
            'tipo_clase'    => $v['tipo'] === 'salida' ? 'badge-danger' : 'badge-info',
            'estado_label'  => $this->labelEstado($v['estado']),
            'estado_clase'  => $this->claseEstado($v['estado']),
            'importe_fmt'   => '$' . number_format((float)($v['importe_total'] ?? 0), 2),
            'fecha_fmt'     => date('d/m/Y', strtotime($v['fecha_emision'])),
            'puede_emitir'  => $v['estado'] === 'borrador',
            'puede_cancelar'=> $v['estado'] === 'borrador' && !(bool)$v['stock_descontado'],
        ]);
    }

    private function formatearDetalle(array $vale): array
    {
        $vale = $this->formatearResumen($vale);
        $total = 0;
        $vale['items'] = array_map(function(array $item) use (&$total): array {
            $total += (float)$item['importe'];
            return array_merge($item, [
                'cant_texto'  => ProductoService::piezasATexto(
                    (int)$item['cantidad_piezas'],
                    (int)$item['unidades_por_caja'],
                    $item['unidad_medida']
                ),
                'precio_fmt'  => '$' . number_format((float)$item['precio_unitario'], 4),
                'importe_fmt' => '$' . number_format((float)$item['importe'], 2),
            ]);
        }, $vale['items']);
        $vale['importe_total_fmt'] = '$' . number_format($total, 2);
        $vale['importe_total_num'] = $total;
        return $vale;
    }

    public function labelEstado(string $estado): string
    {
        return match($estado) {
            'borrador'  => 'Borrador',
            'emitido'   => 'Emitido',
            'entregado' => 'Entregado',
            'cancelado' => 'Cancelado',
            default     => ucfirst($estado),
        };
    }

    public function claseEstado(string $estado): string
    {
        return match($estado) {
            'borrador'  => 'badge-muted',
            'emitido'   => 'badge-success',
            'entregado' => 'badge-info',
            'cancelado' => 'badge-danger',
            default     => 'badge-muted',
        };
    }
}
