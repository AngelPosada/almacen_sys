<?php
/**
 * app/Services/ReporteService.php
 *
 * Lógica de negocio de Reportes y Auditoría.
 *
 * Responsabilidades:
 *   - Normalizar filtros de fecha con valores por defecto
 *   - Formatear datos numéricos para vistas
 *   - Construir estructuras para exportación (Excel/PDF)
 *   - Calcular KPIs derivados
 */

class ReporteService
{
    private ReporteModel $model;

    public function __construct()
    {
        $this->model = new ReporteModel();
    }

    // ----------------------------------------------------------------
    // DATOS PARA CADA SECCIÓN
    // ----------------------------------------------------------------

    /**
     * Sección: Inventario valorizado
     */
    public function getDatosInventario(array $filtros): array
    {
        $productos  = $this->model->getInventarioValoizado($filtros);
        $categorias = $this->model->getResumenPorCategoria();

        // Formatear productos
        $productosFormateados = array_map(function (array $p): array {
            return array_merge($p, [
                'stock_texto'   => ProductoService::piezasATexto(
                    (int) $p['cantidad_piezas'],
                    (int) $p['unidades_por_caja'],
                    $p['unidad_medida']
                ),
                'precio_fmt'    => '$' . number_format((float) $p['precio_unitario'], 4),
                'valor_fmt'     => '$' . number_format((float) $p['valor_total'], 2),
                'estado_clase'  => $this->claseEstadoStock($p['estado_stock']),
                'estado_label'  => $this->labelEstadoStock($p['estado_stock']),
            ]);
        }, $productos);

        // KPIs del inventario
        $valorTotal     = array_sum(array_column($productos, 'valor_total'));
        $totalProductos = count($productos);
        $enCritico      = count(array_filter($productos,
            fn($p) => in_array($p['estado_stock'], ['critico', 'sin_stock'], true)
        ));

        // Formatear categorías
        $categoriasFormateadas = array_map(function (array $c): array {
            return array_merge($c, [
                'valor_fmt' => '$' . number_format((float) $c['valor_total'], 2),
            ]);
        }, $categorias);

        return [
            'productos'   => $productosFormateados,
            'categorias'  => $categoriasFormateadas,
            'kpis'        => [
                'valor_total'      => '$' . number_format($valorTotal, 2),
                'total_productos'  => number_format($totalProductos),
                'productos_critico'=> number_format($enCritico),
                'categorias'       => number_format(count($categorias)),
            ],
        ];
    }

    /**
     * Sección: Movimientos de inventario
     */
    public function getDatosMovimientos(array $filtros, int $pagina, int $porPagina): array
    {
        $filtros  = $this->normalizarFechas($filtros, 30);
        $paginado = $this->model->getMovimientosFiltrados($filtros, $pagina, $porPagina);
        $totales  = $this->model->getTotalesMovimientos($filtros);
        $topSalidas = $this->model->getTopProductosSalidas($filtros);
        $actividad  = $this->model->getActividadPorDia(
            $filtros['fecha_desde'],
            $filtros['fecha_hasta']
        );

        $paginado['items'] = array_map(function (array $m): array {
            $esSalida = $m['cantidad_piezas'] < 0;
            $cantAbs  = abs((int) $m['cantidad_piezas']);
            $upc      = (int) ($m['unidades_por_caja'] ?? 1);
            $unidad   = $m['unidad_medida'] ?? 'pieza';
            return array_merge($m, [
                'es_salida'       => $esSalida,
                'signo'           => $esSalida ? '−' : '+',
                'tipo_clase'      => $esSalida ? 'danger' : 'success',
                'cantidad_texto'  => ProductoService::piezasATexto($cantAbs, $upc, $unidad),
                'anterior_texto'  => ProductoService::piezasATexto((int) $m['stock_anterior'], $upc, $unidad),
                'posterior_texto' => ProductoService::piezasATexto((int) $m['stock_posterior'], $upc, $unidad),
                'fecha_fmt'       => date('d/m/Y H:i', strtotime($m['creado_en'])),
                'origen_label'    => $this->labelOrigen($m['origen']),
            ]);
        }, $paginado['items']);

        // Construir datos del gráfico
        $grafico = $this->construirGraficoMovimientos($actividad, $filtros);

        return [
            'movimientos'  => $paginado,
            'totales'      => [
                'num_movimientos'    => number_format((int)($totales['total_movimientos'] ?? 0)),
                'piezas_entrada'     => number_format((int)($totales['piezas_entrada']     ?? 0)),
                'piezas_salida'      => number_format((int)($totales['piezas_salida']      ?? 0)),
                'num_ajustes'        => number_format((int)($totales['num_ajustes']         ?? 0)),
                'productos_distintos'=> number_format((int)($totales['productos_distintos'] ?? 0)),
                'dias_actividad'     => number_format((int)($totales['dias_con_actividad']  ?? 0)),
            ],
            'top_salidas'  => array_map(function (array $p): array {
                return array_merge($p, [
                    'total_fmt' => number_format((int) $p['total_salidas']) . ' ' . $p['unidad_medida'] . 's',
                ]);
            }, $topSalidas),
            'grafico'      => $grafico,
            'filtros'      => $filtros,
        ];
    }

    /**
     * Sección: Requisiciones
     */
    public function getDatosRequisiciones(array $filtros): array
    {
        $filtros    = $this->normalizarFechas($filtros, 90);
        $lista      = $this->model->getResumenRequisiciones($filtros);
        $kpis       = $this->model->getKpisRequisiciones($filtros);

        $listaFmt = array_map(function (array $r): array {
            return array_merge($r, [
                'total_fmt'    => '$' . number_format((float) $r['total_estimado'], 2),
                'fecha_fmt'    => date('d/m/Y', strtotime($r['fecha_elaboracion'])),
                'estado_clase' => (new RequisicionService())->claseEstado($r['estado']),
                'estado_label' => (new RequisicionService())->labelEstado($r['estado']),
            ]);
        }, $lista);

        return [
            'lista'   => $listaFmt,
            'kpis'    => [
                'total'          => number_format((int)($kpis['total']           ?? 0)),
                'en_proceso'     => number_format((int)($kpis['en_proceso']      ?? 0)),
                'autorizadas'    => number_format((int)($kpis['autorizadas']     ?? 0)),
                'rechazadas'     => number_format((int)($kpis['rechazadas']      ?? 0)),
                'compradas'      => number_format((int)($kpis['compradas']       ?? 0)),
                'con_cotizacion' => number_format((int)($kpis['con_cotizacion']  ?? 0)),
                'monto_total'    => '$' . number_format((float)($kpis['monto_total']    ?? 0), 2),
                'monto_promedio' => '$' . number_format((float)($kpis['monto_promedio'] ?? 0), 2),
            ],
            'filtros' => $filtros,
        ];
    }

    /**
     * Sección: Auditoría
     */
    public function getDatosAuditoria(array $filtros, int $pagina, int $porPagina): array
    {
        $filtros   = $this->normalizarFechas($filtros, 30);
        $paginado  = $this->model->getAuditoriaPaginada($filtros, $pagina, $porPagina);
        $modulos   = $this->model->getModulosAuditoria();
        $usuarios  = $this->model->getUsuariosParaFiltro();
        $topUsuarios = $this->model->getTopUsuariosAuditoria(
            $filtros['fecha_desde'],
            $filtros['fecha_hasta']
        );

        $paginado['items'] = array_map(function (array $a): array {
            return array_merge($a, [
                'fecha_fmt'     => date('d/m/Y H:i:s', strtotime($a['creado_en'])),
                'modulo_label'  => ucfirst($a['modulo']),
                'accion_clase'  => $this->claseAccion($a['accion']),
            ]);
        }, $paginado['items']);

        return [
            'registros'   => $paginado,
            'modulos'     => $modulos,
            'usuarios'    => $usuarios,
            'top_usuarios'=> $topUsuarios,
            'filtros'     => $filtros,
        ];
    }

    /**
     * Datos para el índice general de reportes (resumen ejecutivo).
     */
    public function getDatosResumenGeneral(): array
    {
        $dashModel = new DashboardModel();
        $kpis      = $dashModel->getKpis();
        $resCateg  = $this->model->getResumenPorCategoria();
        $valTotal  = array_sum(array_column(
            $this->model->getInventarioValoizado([]), 'valor_total'
        ));

        return [
            'kpis'         => $kpis,
            'valor_total'  => '$' . number_format($valTotal, 2),
            'categorias'   => array_slice($resCateg, 0, 5),
        ];
    }

    // ----------------------------------------------------------------
    // DATOS PARA EXPORTACIÓN
    // ----------------------------------------------------------------

    /**
     * Retorna estructura lista para PhpSpreadsheet (Fase Composer).
     * Por ahora retorna array estructurado que el controller puede
     * convertir a JSON de descarga o pasar a la capa de Excel.
     */
    public function getEstructuraExportInventario(array $filtros): array
    {
        $productos = $this->model->getInventarioValoizado($filtros);
        $config    = require ROOT_PATH . '/config/config.php';

        $filas = [
            ['headers' => ['Código', 'Nombre', 'Categoría', 'Unidad', 'U/Caja',
                           'Stock (piezas)', 'Stock (texto)', 'Precio Unit.', 'Valor Total', 'Estado']]
        ];

        foreach ($productos as $p) {
            $filas[] = [
                $p['codigo'],
                $p['nombre'],
                $p['categoria_nombre'],
                $p['unidad_medida'],
                $p['unidades_por_caja'],
                $p['cantidad_piezas'],
                ProductoService::piezasATexto((int)$p['cantidad_piezas'], (int)$p['unidades_por_caja'], $p['unidad_medida']),
                $p['precio_unitario'],
                $p['valor_total'],
                $p['estado_stock'],
            ];
        }

        return [
            'titulo'       => 'Reporte de Inventario',
            'institucion'  => $config['institucion']['nombre'] ?? '',
            'fecha'        => date('d/m/Y H:i'),
            'generado_por' => $_SESSION['usuario_nombre'] ?? '',
            'filas'        => $filas,
        ];
    }

    // ----------------------------------------------------------------
    // HELPERS PRIVADOS
    // ----------------------------------------------------------------

    /**
     * Asegura que fecha_desde y fecha_hasta siempre estén presentes.
     */
    private function normalizarFechas(array $filtros, int $diasPorDefecto = 30): array
    {
        if (empty($filtros['fecha_hasta'])) {
            $filtros['fecha_hasta'] = date('Y-m-d');
        }
        if (empty($filtros['fecha_desde'])) {
            $filtros['fecha_desde'] = date('Y-m-d', strtotime("-{$diasPorDefecto} days"));
        }
        return $filtros;
    }

    private function construirGraficoMovimientos(array $actividad, array $filtros): array
    {
        $inicio  = strtotime($filtros['fecha_desde']);
        $fin     = strtotime($filtros['fecha_hasta']);
        $porFecha = [];
        foreach ($actividad as $fila) {
            $porFecha[$fila['fecha']] = $fila;
        }

        $labels   = [];
        $entradas = [];
        $salidas  = [];
        $dias     = 0;

        for ($ts = $inicio; $ts <= $fin && $dias < 60; $ts += 86400, $dias++) {
            $fecha    = date('Y-m-d', $ts);
            $labels[] = date('d/m', $ts);
            $entradas[] = (int) ($porFecha[$fecha]['entradas']            ?? 0);
            $salidas[]  = (int) ($porFecha[$fecha]['salidas']             ?? 0);
        }

        return compact('labels', 'entradas', 'salidas');
    }

    private function claseEstadoStock(string $estado): string
    {
        return match ($estado) {
            'sin_stock' => 'badge-danger',
            'critico'   => 'badge-danger',
            'bajo'      => 'badge-warning',
            default     => 'badge-success',
        };
    }

    private function labelEstadoStock(string $estado): string
    {
        return match ($estado) {
            'sin_stock' => 'Sin stock',
            'critico'   => 'Crítico',
            'bajo'      => 'Bajo',
            default     => 'OK',
        };
    }

    private function labelOrigen(string $origen): string
    {
        return match ($origen) {
            'compra'            => 'Compra',
            'devolucion'        => 'Devolución',
            'vale_salida'       => 'Vale de salida',
            'ajuste_manual'     => 'Ajuste manual',
            'inventario_fisico' => 'Inv. físico',
            default             => ucfirst($origen),
        };
    }

    private function claseAccion(string $accion): string
    {
        if (str_contains($accion, 'crear') || str_contains($accion, 'login')) return 'badge-success';
        if (str_contains($accion, 'elim') || str_contains($accion, 'cancel') || str_contains($accion, 'fallido')) return 'badge-danger';
        if (str_contains($accion, 'edit') || str_contains($accion, 'estado')) return 'badge-info';
        return 'badge-muted';
    }
}
