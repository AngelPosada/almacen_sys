<?php
/**
 * app/Services/DashboardService.php
 *
 * Lógica de negocio del Dashboard.
 * Transforma datos crudos del Model en estructuras listas para la vista.
 *
 * Responsabilidades:
 *   - Formatear montos en pesos mexicanos
 *   - Calcular porcentajes y tendencias
 *   - Preparar datos para gráficos (JSON seguro para JS)
 *   - Traducir enums a etiquetas legibles
 */

class DashboardService
{
    private DashboardModel $model;

    public function __construct()
    {
        $this->model = new DashboardModel();
    }

    // ----------------------------------------------------------------
    // DATOS COMPLETOS PARA LA VISTA PRINCIPAL
    // ----------------------------------------------------------------

    /**
     * Reúne y formatea todos los datos del dashboard.
     * Llamado por DashboardController@index.
     */
    public function getDashboardData(): array
    {
        $kpis                = $this->model->getKpis();
        $ultimosMovimientos  = $this->model->ultimosMovimientos();
        $productosStockBajo  = $this->model->productosStockBajo();
        $ultimosPedidos      = $this->model->ultimosPedidos();
        $actividad           = $this->model->actividadUltimos14Dias();

        return [
            'kpis'                => $this->formatKpis($kpis),
            'ultimos_movimientos' => $this->formatMovimientos($ultimosMovimientos),
            'stock_bajo'          => $this->formatStockBajo($productosStockBajo),
            'ultimos_pedidos'     => $this->formatPedidos($ultimosPedidos),
            'grafico_actividad'   => $this->buildGraficoActividad($actividad),
        ];
    }

    /**
     * Solo KPIs — para el endpoint AJAX /dashboard/stats.
     */
    public function getStats(): array
    {
        $kpis = $this->model->getKpis();
        return $this->formatKpis($kpis);
    }

    // ----------------------------------------------------------------
    // FORMATEADORES PRIVADOS
    // ----------------------------------------------------------------

    private function formatKpis(array $kpis): array
    {
        return [
            'total_productos' => [
                'valor'     => number_format($kpis['total_productos']),
                'etiqueta'  => 'Productos en catálogo',
                'icono'     => 'ti-package',
                'tipo'      => 'primary',
            ],
            'valor_inventario' => [
                'valor'     => '$' . number_format($kpis['valor_inventario'], 2),
                'etiqueta'  => 'Valor del inventario',
                'icono'     => 'ti-currency-dollar',
                'tipo'      => 'secondary',
            ],
            'stock_critico' => [
                'valor'     => number_format($kpis['productos_stock_critico']),
                'etiqueta'  => 'Productos en alerta',
                'icono'     => 'ti-alert-triangle',
                'tipo'      => $kpis['productos_stock_critico'] > 0 ? 'accent' : 'primary',
                'alerta'    => $kpis['productos_stock_critico'] > 0,
            ],
            'pedidos_pendientes' => [
                'valor'     => number_format($kpis['pedidos_pendientes']),
                'etiqueta'  => 'Pedidos por atender',
                'icono'     => 'ti-shopping-cart',
                'tipo'      => $kpis['pedidos_pendientes'] > 0 ? 'warning' : 'primary',
            ],
            'requisiciones' => [
                'valor'     => number_format($kpis['requisiciones_pendientes']),
                'etiqueta'  => 'Requisiciones activas',
                'icono'     => 'ti-file-text',
                'tipo'      => 'secondary',
            ],
            'movimientos_mes' => [
                'valor'     => number_format($kpis['movimientos_mes']),
                'etiqueta'  => 'Movimientos este mes',
                'icono'     => 'ti-activity',
                'tipo'      => 'primary',
            ],
        ];
    }

    private function formatMovimientos(array $movimientos): array
    {
        return array_map(function (array $m): array {
            $esSalida   = $m['cantidad_piezas'] < 0;
            $cantAbs    = abs($m['cantidad_piezas']);

            return [
                'id'               => $m['id'],
                'tipo'             => $m['tipo'],
                'tipo_label'       => $this->labelTipoMovimiento($m['tipo']),
                'tipo_clase'       => $esSalida ? 'danger' : 'success',
                'tipo_icono'       => $esSalida ? 'ti-arrow-up-right' : 'ti-arrow-down-left',
                'signo'            => $esSalida ? '-' : '+',
                'cantidad'         => number_format($cantAbs) . ' pz.',
                'origen'           => $this->labelOrigen($m['origen']),
                'producto_nombre'  => $m['producto_nombre'],
                'producto_codigo'  => $m['producto_codigo'],
                'usuario_nombre'   => $m['usuario_nombre'],
                'fecha'            => $this->fechaRelativa($m['creado_en']),
                'fecha_completa'   => date('d/m/Y H:i', strtotime($m['creado_en'])),
            ];
        }, $movimientos);
    }

    private function formatStockBajo(array $productos): array
    {
        return array_map(function (array $p): array {
            // Porcentaje de stock actual vs mínimo (para la barra visual)
            $minimo    = max($p['stock_minimo'], 1);
            $porcentaje = min(round(($p['cantidad_piezas'] / $minimo) * 100), 100);

            // Presentación visual: "X cajas + Y piezas"
            $presentacion = '';
            if ($p['unidades_por_caja'] > 1) {
                if ($p['cajas_completas'] > 0) {
                    $presentacion .= $p['cajas_completas'] . ' caja' . ($p['cajas_completas'] > 1 ? 's' : '');
                }
                if ($p['piezas_sueltas'] > 0) {
                    $presentacion .= ($p['cajas_completas'] > 0 ? ' + ' : '') . $p['piezas_sueltas'] . ' pz.';
                }
                if ($p['cajas_completas'] === 0 && $p['piezas_sueltas'] === 0) {
                    $presentacion = 'Sin stock';
                }
            } else {
                $presentacion = $p['cantidad_piezas'] > 0
                    ? number_format($p['cantidad_piezas']) . ' ' . $p['unidad_medida']
                    : 'Sin stock';
            }

            return [
                'id'             => $p['producto_id'],
                'codigo'         => $p['codigo'],
                'nombre'         => $p['nombre'],
                'categoria'      => $p['categoria_nombre'],
                'presentacion'   => $presentacion,
                'piezas_totales' => number_format($p['cantidad_piezas']),
                'stock_minimo'   => number_format($p['stock_minimo']),
                'porcentaje'     => $porcentaje,
                'estado'         => $p['estado_stock'],
                'barra_clase'    => match ($p['estado_stock']) {
                    'sin_stock' => 'critical',
                    'critico'   => 'critical',
                    'bajo'      => 'warning',
                    default     => 'ok',
                },
                'badge_clase'    => match ($p['estado_stock']) {
                    'sin_stock' => 'badge-danger',
                    'critico'   => 'badge-danger',
                    'bajo'      => 'badge-warning',
                    default     => 'badge-success',
                },
                'badge_label'    => match ($p['estado_stock']) {
                    'sin_stock' => 'Sin stock',
                    'critico'   => 'Crítico',
                    'bajo'      => 'Bajo',
                    default     => 'OK',
                },
            ];
        }, $productos);
    }

    private function formatPedidos(array $pedidos): array
    {
        return array_map(function (array $p): array {
            return [
                'id'          => $p['id'],
                'folio'       => $p['folio'],
                'solicitante' => $p['solicitante'],
                'total_items' => $p['total_items'],
                'prioridad'   => $p['prioridad'],
                'estado'      => $p['estado'],
                'estado_label'=> $this->labelEstadoPedido($p['estado']),
                'estado_clase'=> $this->claseBadgeEstado($p['estado']),
                'es_urgente'  => $p['prioridad'] === 'urgente',
                'fecha'       => $this->fechaRelativa($p['creado_en']),
            ];
        }, $pedidos);
    }

    /**
     * Construye los datos del gráfico de actividad en formato seguro para JSON/JS.
     * Rellena días sin actividad con ceros.
     */
    private function buildGraficoActividad(array $actividad): array
    {
        // Indexar por fecha para acceso rápido
        $porFecha = [];
        foreach ($actividad as $fila) {
            $porFecha[$fila['fecha']] = $fila;
        }

        $labels   = [];
        $entradas = [];
        $salidas  = [];

        for ($i = 13; $i >= 0; $i--) {
            $fecha   = date('Y-m-d', strtotime("-{$i} days"));
            $etiqueta = $i === 0 ? 'Hoy' : ($i === 1 ? 'Ayer' : date('d/m', strtotime($fecha)));

            $labels[]   = $etiqueta;
            $entradas[] = (int) ($porFecha[$fecha]['entradas'] ?? 0);
            $salidas[]  = (int) ($porFecha[$fecha]['salidas']  ?? 0);
        }

        return compact('labels', 'entradas', 'salidas');
    }

    // ----------------------------------------------------------------
    // HELPERS DE TRADUCCIÓN
    // ----------------------------------------------------------------

    private function labelTipoMovimiento(string $tipo): string
    {
        return match ($tipo) {
            'entrada'    => 'Entrada',
            'salida'     => 'Salida',
            'ajuste'     => 'Ajuste',
            'devolucion' => 'Devolución',
            default      => ucfirst($tipo),
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

    private function labelEstadoPedido(string $estado): string
    {
        return match ($estado) {
            'pendiente'          => 'Pendiente',
            'en_proceso'         => 'En proceso',
            'entregado_parcial'  => 'Parcial',
            'entregado'          => 'Entregado',
            'cancelado'          => 'Cancelado',
            default              => ucfirst($estado),
        };
    }

    private function claseBadgeEstado(string $estado): string
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

    /**
     * Convierte una fecha a texto relativo legible.
     * "Hace 5 minutos", "Ayer", "Hace 3 días", etc.
     */
    private function fechaRelativa(string $fecha): string
    {
        $diff = time() - strtotime($fecha);

        if ($diff < 60)     return 'Hace unos segundos';
        if ($diff < 3600)   return 'Hace ' . floor($diff / 60) . ' min';
        if ($diff < 86400)  return 'Hace ' . floor($diff / 3600) . ' h';
        if ($diff < 172800) return 'Ayer';
        if ($diff < 604800) return 'Hace ' . floor($diff / 86400) . ' días';

        return date('d/m/Y', strtotime($fecha));
    }
}
