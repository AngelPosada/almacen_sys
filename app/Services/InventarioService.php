<?php
/**
 * app/Services/InventarioService.php
 *
 * Lógica de negocio de movimientos de inventario.
 *
 * Responsabilidades:
 *   - Validar inputs antes de mover stock
 *   - Convertir cajas+piezas a piezas base (regla crítica)
 *   - Orquestar movimientos atómicos via InventarioModel
 *   - Formatear datos para vistas
 *   - Verificar permisos por tipo de operación
 */

class InventarioService
{
    private InventarioModel $inventarioModel;
    private ProductoModel   $productoModel;

    public function __construct()
    {
        $this->inventarioModel = new InventarioModel();
        $this->productoModel   = new ProductoModel();
    }

    // ----------------------------------------------------------------
    // ENTRADA DE STOCK
    // ----------------------------------------------------------------

    /**
     * Registra una entrada de mercancía al almacén.
     *
     * El formulario puede expresar la cantidad en CAJAS o en PIEZAS.
     * Este servicio convierte todo a piezas base antes de persistir.
     *
     * @param array $input  Datos del formulario:
     *   - producto_id     int
     *   - cajas           int  (puede ser 0)
     *   - piezas_sueltas  int  (puede ser 0)
     *   - origen          string
     *   - observacion     string
     *
     * @return array ['ok' => bool, 'data' => [...] | 'errors' => [...]]
     */
    public function registrarEntrada(array $input): array
    {
        $validado = $this->validarEntrada($input);
        if (!empty($validado['errors'])) {
            return ['ok' => false, 'errors' => $validado['errors']];
        }

        $producto = $this->productoModel->findById($validado['producto_id']);
        if (!$producto) {
            return ['ok' => false, 'errors' => ['producto_id' => 'Producto no encontrado.']];
        }

        // Conversión CAJAS + PIEZAS SUELTAS → PIEZAS BASE
        $piezasBase = ProductoService::cajasAPiezas(
            $validado['cajas'],
            $validado['piezas_sueltas'],
            (int) $producto['unidades_por_caja']
        );

        if ($piezasBase <= 0) {
            return ['ok' => false, 'errors' => [
                'cajas' => 'La cantidad total debe ser mayor a cero.',
            ]];
        }

        try {
            $resultado = $this->inventarioModel->moverStock(
                productoId:     $validado['producto_id'],
                cantidadPiezas: $piezasBase,          // siempre positivo en entradas
                tipo:           'entrada',
                origen:         $validado['origen'],
                usuarioId:      (int) $_SESSION['usuario_id'],
                observacion:    $validado['observacion'],
                refTipo:        null,
                refId:          null
            );

            AuditoriaService::log(
                'inventario', 'entrada',
                $validado['producto_id'],
                "Entrada: +{$piezasBase} pz. de {$producto['nombre']} ({$validado['origen']})"
            );

            Logger::info('INVENTARIO', "Entrada registrada", [
                'producto'  => $producto['codigo'],
                'piezas'    => $piezasBase,
                'anterior'  => $resultado['stock_anterior'],
                'posterior' => $resultado['stock_posterior'],
            ]);

            return [
                'ok'   => true,
                'data' => [
                    'movimiento_id'   => $resultado['movimiento_id'],
                    'producto_nombre' => $producto['nombre'],
                    'piezas_base'     => $piezasBase,
                    'stock_anterior'  => $resultado['stock_anterior'],
                    'stock_posterior' => $resultado['stock_posterior'],
                    'stock_texto'     => ProductoService::piezasATexto(
                        $resultado['stock_posterior'],
                        (int) $producto['unidades_por_caja'],
                        $producto['unidad_medida']
                    ),
                ],
            ];

        } catch (RuntimeException $e) {
            return ['ok' => false, 'errors' => ['general' => $e->getMessage()]];
        } catch (Throwable $e) {
            Logger::error('INVENTARIO', 'Error en entrada: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['general' => 'Error interno al registrar la entrada.']];
        }
    }

    // ----------------------------------------------------------------
    // SALIDA DE STOCK (ajuste manual — no por vale)
    // ----------------------------------------------------------------

    /**
     * Registra una salida directa de stock (merma, préstamo, corrección).
     * Las salidas por entrega de pedido se registran desde ValeService,
     * no desde aquí.
     */
    public function registrarSalida(array $input): array
    {
        $validado = $this->validarSalida($input);
        if (!empty($validado['errors'])) {
            return ['ok' => false, 'errors' => $validado['errors']];
        }

        $producto = $this->productoModel->findById($validado['producto_id']);
        if (!$producto) {
            return ['ok' => false, 'errors' => ['producto_id' => 'Producto no encontrado.']];
        }

        // Verificar stock disponible antes de intentar el movimiento
        $stockActual = $this->inventarioModel->getStock($validado['producto_id']);

        $piezasBase = ProductoService::cajasAPiezas(
            $validado['cajas'],
            $validado['piezas_sueltas'],
            (int) $producto['unidades_por_caja']
        );

        if ($piezasBase <= 0) {
            return ['ok' => false, 'errors' => [
                'cajas' => 'La cantidad total debe ser mayor a cero.',
            ]];
        }

        if ($stockActual < $piezasBase) {
            $disponible = ProductoService::piezasATexto(
                $stockActual,
                (int) $producto['unidades_por_caja'],
                $producto['unidad_medida']
            );
            return ['ok' => false, 'errors' => [
                'cajas' => "Stock insuficiente. Disponible: {$disponible}.",
            ]];
        }

        try {
            $resultado = $this->inventarioModel->moverStock(
                productoId:     $validado['producto_id'],
                cantidadPiezas: -$piezasBase,       // negativo en salidas
                tipo:           'salida',
                origen:         $validado['origen'],
                usuarioId:      (int) $_SESSION['usuario_id'],
                observacion:    $validado['observacion']
            );

            AuditoriaService::log(
                'inventario', 'salida',
                $validado['producto_id'],
                "Salida: -{$piezasBase} pz. de {$producto['nombre']} ({$validado['origen']})"
            );

            return [
                'ok'   => true,
                'data' => [
                    'movimiento_id'   => $resultado['movimiento_id'],
                    'producto_nombre' => $producto['nombre'],
                    'piezas_base'     => $piezasBase,
                    'stock_anterior'  => $resultado['stock_anterior'],
                    'stock_posterior' => $resultado['stock_posterior'],
                    'stock_texto'     => ProductoService::piezasATexto(
                        $resultado['stock_posterior'],
                        (int) $producto['unidades_por_caja'],
                        $producto['unidad_medida']
                    ),
                ],
            ];

        } catch (RuntimeException $e) {
            return ['ok' => false, 'errors' => ['general' => $e->getMessage()]];
        } catch (Throwable $e) {
            Logger::error('INVENTARIO', 'Error en salida: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['general' => 'Error interno al registrar la salida.']];
        }
    }

    // ----------------------------------------------------------------
    // AJUSTE DE INVENTARIO FÍSICO
    // ----------------------------------------------------------------

    /**
     * Ajusta el stock a un valor exacto contado físicamente.
     * Solo Admin y Almacenista pueden hacer ajustes.
     */
    public function registrarAjuste(array $input): array
    {
        $productoId  = (int) ($input['producto_id'] ?? 0);
        $nuevoPiezas = (int) ($input['nuevo_stock_piezas'] ?? -1);
        $observacion = Security::sanitize($input['observacion'] ?? '');

        $errores = [];
        if ($productoId <= 0)   $errores['producto_id']         = 'Selecciona un producto.';
        if ($nuevoPiezas < 0)   $errores['nuevo_stock_piezas']  = 'El stock no puede ser negativo.';
        if (strlen($observacion) < 5) $errores['observacion']   = 'Describe el motivo del ajuste (mín. 5 caracteres).';

        if (!empty($errores)) {
            return ['ok' => false, 'errors' => $errores];
        }

        $producto = $this->productoModel->findById($productoId);
        if (!$producto) {
            return ['ok' => false, 'errors' => ['producto_id' => 'Producto no encontrado.']];
        }

        try {
            $resultado = $this->inventarioModel->ajustarStock(
                $productoId,
                $nuevoPiezas,
                (int) $_SESSION['usuario_id'],
                $observacion
            );

            $diff = $resultado['stock_posterior'] - $resultado['stock_anterior'];
            AuditoriaService::log(
                'inventario', 'ajuste',
                $productoId,
                "Ajuste físico: {$resultado['stock_anterior']} → {$resultado['stock_posterior']} pz. " .
                "({$observacion})"
            );

            return ['ok' => true, 'data' => $resultado];

        } catch (Throwable $e) {
            Logger::error('INVENTARIO', 'Error en ajuste: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['general' => 'Error interno al registrar el ajuste.']];
        }
    }

    // ----------------------------------------------------------------
    // DATOS PARA VISTAS
    // ----------------------------------------------------------------

    public function getDatosIndex(): array
    {
        $totales    = $this->inventarioModel->getTotalesHoy();
        $movimientos = $this->inventarioModel->getMovimientos([], 1, 20);

        return [
            'totales'    => $totales,
            'movimientos'=> $this->formatearMovimientos($movimientos['items']),
            'paginacion' => $movimientos,
        ];
    }

    public function getDatosEntradas(): array
    {
        $productos = $this->productoModel->getParaSelect();
        $recientes = $this->inventarioModel->getEntradasHoy();

        return [
            'productos' => $productos,
            'recientes' => $this->formatearMovimientos($recientes),
        ];
    }

    public function getDatosSalidas(): array
    {
        $productos = $this->productoModel->getParaSelect();
        $recientes = $this->inventarioModel->getSalidasHoy();

        return [
            'productos' => $productos,
            'recientes' => $this->formatearMovimientos($recientes),
        ];
    }

    public function getMovimientosPaginados(array $filtros, int $pagina, int $porPagina): array
    {
        $resultado = $this->inventarioModel->getMovimientos($filtros, $pagina, $porPagina);
        $resultado['items'] = $this->formatearMovimientos($resultado['items']);
        return $resultado;
    }

    // ----------------------------------------------------------------
    // VALIDACIONES PRIVADAS
    // ----------------------------------------------------------------

    private function validarEntrada(array $input): array
    {
        $errores = [];

        $productoId   = (int)    ($input['producto_id']    ?? 0);
        $cajas        = max(0, (int) ($input['cajas']       ?? 0));
        $piezasSueltas= max(0, (int) ($input['piezas_sueltas'] ?? 0));
        $origen       = Security::sanitize($input['origen'] ?? 'compra');
        $observacion  = Security::sanitize($input['observacion'] ?? '');

        $origenesValidos = ['compra', 'devolucion', 'ajuste_manual', 'inventario_fisico'];

        if ($productoId <= 0) {
            $errores['producto_id'] = 'Selecciona un producto.';
        }
        if ($cajas === 0 && $piezasSueltas === 0) {
            $errores['cajas'] = 'Ingresa al menos una caja o una pieza.';
        }
        if (!in_array($origen, $origenesValidos, true)) {
            $errores['origen'] = 'Origen no válido.';
        }

        return compact('productoId', 'cajas', 'piezasSueltas', 'origen', 'observacion', 'errores')
            + ['producto_id' => $productoId, 'errors' => $errores];
    }

    private function validarSalida(array $input): array
    {
        $validado          = $this->validarEntrada($input);
        // Para salidas el origen tiene diferentes opciones válidas
        $validado['origen'] = Security::sanitize($input['origen'] ?? 'ajuste_manual');
        return $validado;
    }

    // ----------------------------------------------------------------
    // FORMATEO
    // ----------------------------------------------------------------

    private function formatearMovimientos(array $movimientos): array
    {
        return array_map(function (array $m): array {
            $esSalida   = $m['cantidad_piezas'] < 0;
            $cantAbs    = abs((int) $m['cantidad_piezas']);
            $upc        = (int) ($m['unidades_por_caja'] ?? 1);
            $unidad     = $m['unidad_medida'] ?? 'pieza';

            return array_merge($m, [
                'es_salida'       => $esSalida,
                'signo'           => $esSalida ? '−' : '+',
                'tipo_clase'      => $esSalida ? 'danger' : 'success',
                'tipo_icono'      => $esSalida ? 'ti-arrow-up-right' : 'ti-arrow-down-left',
                'cantidad_texto'  => ProductoService::piezasATexto($cantAbs, $upc, $unidad),
                'anterior_texto'  => ProductoService::piezasATexto((int) $m['stock_anterior'], $upc, $unidad),
                'posterior_texto' => ProductoService::piezasATexto((int) $m['stock_posterior'], $upc, $unidad),
                'fecha_fmt'       => date('d/m/Y H:i', strtotime($m['creado_en'])),
                'origen_label'    => $this->labelOrigen($m['origen'] ?? ''),
                'tipo_label'      => ucfirst($m['tipo'] ?? ''),
            ]);
        }, $movimientos);
    }

    private function labelOrigen(string $origen): string
    {
        return match ($origen) {
            'compra'            => 'Compra',
            'devolucion'        => 'Devolución',
            'vale_salida'       => 'Vale de salida',
            'ajuste_manual'     => 'Ajuste manual',
            'inventario_fisico' => 'Inventario físico',
            default             => ucfirst($origen),
        };
    }
}
