<?php
/**
 * app/Models/DashboardModel.php
 *
 * Consultas de datos para el Dashboard.
 * Solo lectura — agrega información de múltiples tablas.
 * Todas las consultas están optimizadas para ejecución rápida.
 */

class DashboardModel extends BaseModel
{
    // ----------------------------------------------------------------
    // KPIs PRINCIPALES
    // ----------------------------------------------------------------

    /**
     * Total de productos activos en catálogo.
     */
    public function totalProductos(): int
    {
        return $this->count(
            'SELECT COUNT(*) FROM productos
             WHERE activo = 1 AND eliminado_en IS NULL',
        );
    }

    /**
     * Valor total del inventario actual.
     * Suma: stock_piezas × precio_unitario por cada producto.
     */
    public function valorInventario(): float
    {
        $result = $this->fetchOne(
            'SELECT COALESCE(SUM(s.cantidad_piezas * p.precio_unitario), 0) AS total
             FROM stock s
             JOIN productos p ON p.id = s.producto_id
             WHERE p.activo = 1 AND p.eliminado_en IS NULL',
        );
        return (float) ($result["total"] ?? 0);
    }

    /**
     * Conteo de productos con stock crítico o sin stock.
     * Crítico = cantidad_piezas <= stock_minimo
     */
    public function productosStockCritico(): int
    {
        return $this->count(
            'SELECT COUNT(*)
              FROM productos p
              LEFT JOIN stock s ON s.producto_id = p.id
              WHERE p.activo = 1
                AND p.eliminado_en IS NULL
                AND COALESCE(s.cantidad_piezas, 0) <= p.stock_minimo',
        );
    }

    /**
     * Pedidos pendientes de atender.
     */
    public function pedidosPendientes(): int
    {
        return $this->count(
            'SELECT COUNT(*) FROM pedidos
             WHERE estado IN ("pendiente", "en_proceso")
             AND eliminado_en IS NULL',
        );
    }

    /**
     * Requisiciones en proceso (enviadas o validadas, sin cerrar).
     */
    public function requisicionesPendientes(): int
    {
        return $this->count(
            'SELECT COUNT(*) FROM requisiciones
             WHERE estado IN ("enviada", "validada", "autorizada")
             AND eliminado_en IS NULL',
        );
    }

    /**
     * Movimientos registrados en los últimos 30 días.
     */
    public function movimientosEsteMes(): int
    {
        return $this->count(
            'SELECT COUNT(*) FROM movimientos
             WHERE creado_en >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
        );
    }

    // ----------------------------------------------------------------
    // TABLAS DEL DASHBOARD
    // ----------------------------------------------------------------

    /**
     * Últimos 8 movimientos de inventario (entradas y salidas).
     */
    public function ultimosMovimientos(): array
    {
        return $this->fetchAll(
            'SELECT
               m.id,
               m.tipo,
               m.cantidad_piezas,
               m.origen,
               m.creado_en,
               p.nombre  AS producto_nombre,
               p.codigo  AS producto_codigo,
               CONCAT(u.nombre, " ", u.apellidos) AS usuario_nombre
             FROM movimientos m
             JOIN productos p ON p.id = m.producto_id
             JOIN usuarios  u ON u.id = m.usuario_id
             ORDER BY m.creado_en DESC
             LIMIT 8',
        );
    }

    /**
     * Top 6 productos con stock más bajo respecto a su mínimo.
     * Ordenados por urgencia (ratio stock_actual / stock_minimo ASC).
     */
    public function productosStockBajo(): array
    {
        return $this->fetchAll(
            'SELECT
                p.id                                                          AS producto_id,
                p.codigo,
                p.nombre,
                COALESCE(s.cantidad_piezas, 0)                               AS cantidad_piezas,
                p.stock_minimo,
                FLOOR(COALESCE(s.cantidad_piezas,0) / p.unidades_por_caja)  AS cajas_completas,
                MOD(COALESCE(s.cantidad_piezas,0), p.unidades_por_caja)     AS piezas_sueltas,
                p.unidades_por_caja,
                p.unidad_medida,
                c.nombre                                                     AS categoria_nombre,
                CASE
                  WHEN COALESCE(s.cantidad_piezas,0) = 0               THEN "sin_stock"
                  WHEN COALESCE(s.cantidad_piezas,0) <= p.stock_minimo THEN "critico"
                  WHEN COALESCE(s.cantidad_piezas,0) <= (p.stock_minimo * 1.5) THEN "bajo"
                  ELSE "ok"
                END AS estado_stock
              FROM productos p
              LEFT JOIN stock      s ON s.producto_id = p.id
              LEFT JOIN categorias c ON c.id = p.categoria_id
              WHERE p.activo = 1
                AND p.eliminado_en IS NULL
                AND COALESCE(s.cantidad_piezas, 0) <= (p.stock_minimo * 1.5)
              ORDER BY
                COALESCE(s.cantidad_piezas, 0) ASC,
                (COALESCE(s.cantidad_piezas,0) / GREATEST(p.stock_minimo, 1)) ASC
              LIMIT 6',
        );
    }

    /**
     * Últimos 5 pedidos registrados con su estado.
     */
    public function ultimosPedidos(): array
    {
        return $this->fetchAll(
            'SELECT
               p.id,
               p.folio,
               p.estado,
               p.prioridad,
               p.creado_en,
               CONCAT(u.nombre, " ", u.apellidos) AS solicitante,
               COUNT(pi.id) AS total_items
             FROM pedidos p
             JOIN usuarios u      ON u.id  = p.solicitante_id
             JOIN pedido_items pi ON pi.pedido_id = p.id
             WHERE p.eliminado_en IS NULL
             GROUP BY p.id
             ORDER BY p.creado_en DESC
             LIMIT 5',
        );
    }

    /**
     * Actividad de movimientos por día (últimos 14 días).
     * Para el mini gráfico de barras del dashboard.
     */
    public function actividadUltimos14Dias(): array
    {
        return $this->fetchAll(
            'SELECT
               DATE(creado_en)          AS fecha,
               COUNT(*)                 AS total,
               SUM(cantidad_piezas > 0) AS entradas,
               SUM(cantidad_piezas < 0) AS salidas
             FROM movimientos
             WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(creado_en)
             ORDER BY fecha ASC',
        );
    }

    // ----------------------------------------------------------------
    // STATS RÁPIDOS (endpoint AJAX /dashboard/stats)
    // ----------------------------------------------------------------

    /**
     * Todos los KPIs en una sola llamada para refresco AJAX.
     */
    public function getKpis(): array
    {
        return [
            "total_productos" => $this->totalProductos(),
            "valor_inventario" => $this->valorInventario(),
            "productos_stock_critico" => $this->productosStockCritico(),
            "pedidos_pendientes" => $this->pedidosPendientes(),
            "requisiciones_pendientes" => $this->requisicionesPendientes(),
            "movimientos_mes" => $this->movimientosEsteMes(),
        ];
    }
}
