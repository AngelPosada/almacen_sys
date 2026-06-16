<?php
/**
 * app/Models/ReporteModel.php
 *
 * Consultas analíticas del sistema.
 * Solo lectura — agrega datos de múltiples tablas para reportes.
 *
 * Principio: cada sección tiene sus propias consultas.
 * No se reutiliza lógica de otros modelos (evita acoplamiento).
 */

class ReporteModel extends BaseModel
{
    // ----------------------------------------------------------------
    // REPORTE: INVENTARIO VALORIZADO
    // ----------------------------------------------------------------

    /**
     * Inventario completo con valor total por producto.
     * Ordenable por distintos criterios.
     */
    public function getInventarioValoizado(array $filtros = []): array
    {
        $where  = ["p.activo = 1", "p.eliminado_en IS NULL"];
        $params = [];

        if (!empty($filtros['categoria_id'])) {
            $where[]              = 'p.categoria_id = :cat_id';
            $params[':cat_id']    = (int) $filtros['categoria_id'];
        }
        if (!empty($filtros['estado_stock'])) {
            $where[]              = 'vsp.estado_stock = :est';
            $params[':est']       = $filtros['estado_stock'];
        }

        $orderMap = [
            'nombre'   => 'p.nombre ASC',
            'valor'    => 'valor_total DESC',
            'stock'    => 'vsp.cantidad_piezas DESC',
            'categoria'=> 'c.nombre ASC, p.nombre ASC',
        ];
        $order = $orderMap[$filtros['orden'] ?? ''] ?? 'p.nombre ASC';

        return $this->fetchAll(
            "SELECT
               p.id,
               p.codigo,
               p.nombre,
               p.unidad_medida,
               p.unidades_por_caja,
               p.presentacion,
               p.precio_unitario,
               p.stock_minimo,
               c.nombre                          AS categoria_nombre,
               COALESCE(s.cantidad_piezas, 0)                               AS cantidad_piezas,
               FLOOR(COALESCE(s.cantidad_piezas,0)/p.unidades_por_caja)       AS cajas_completas,
               MOD(COALESCE(s.cantidad_piezas,0), p.unidades_por_caja)        AS piezas_sueltas,
               CASE
                 WHEN COALESCE(s.cantidad_piezas,0) = 0                      THEN 'sin_stock'
                 WHEN COALESCE(s.cantidad_piezas,0) <= p.stock_minimo        THEN 'critico'
                 WHEN COALESCE(s.cantidad_piezas,0) <= (p.stock_minimo*1.5)  THEN 'bajo'
                 ELSE 'ok'
               END                                                             AS estado_stock,
               ROUND(
                 COALESCE(s.cantidad_piezas,0) * p.precio_unitario, 2
               )                                                               AS valor_total
             FROM productos p
             JOIN  categorias c ON c.id = p.categoria_id
             LEFT JOIN stock   s ON s.producto_id = p.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY {$order}",
            $params
        );
    }

    /**
     * Resumen por categoría: total productos, total piezas, valor acumulado.
     */
    public function getResumenPorCategoria(): array
    {
        return $this->fetchAll(
            "SELECT
               c.nombre                                        AS categoria,
               COUNT(p.id)                                    AS total_productos,
               SUM(COALESCE(s.cantidad_piezas, 0))            AS total_piezas,
               ROUND(SUM(
                 COALESCE(s.cantidad_piezas, 0) * p.precio_unitario
               ), 2)                                          AS valor_total,
               SUM(CASE WHEN
                 COALESCE(s.cantidad_piezas,0) <= p.stock_minimo
                 THEN 1 ELSE 0 END)                           AS productos_criticos
             FROM categorias c
             JOIN productos p  ON p.categoria_id = c.id
                               AND p.activo = 1
                               AND p.eliminado_en IS NULL
             LEFT JOIN stock s ON s.producto_id = p.id
             GROUP BY c.id
             ORDER BY valor_total DESC"
        );
    }

    // ----------------------------------------------------------------
    // REPORTE: MOVIMIENTOS
    // ----------------------------------------------------------------

    /**
     * Movimientos con filtros avanzados y totales acumulados.
     */
    public function getMovimientosFiltrados(array $filtros = [], int $pagina = 1, int $porPagina = 50): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['tipo'])) {
            $where[]         = 'm.tipo = :tipo';
            $params[':tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['origen'])) {
            $where[]            = 'm.origen = :origen';
            $params[':origen']  = $filtros['origen'];
        }
        if (!empty($filtros['producto_id'])) {
            $where[]            = 'm.producto_id = :pid';
            $params[':pid']     = (int) $filtros['producto_id'];
        }
        if (!empty($filtros['usuario_id'])) {
            $where[]            = 'm.usuario_id = :uid';
            $params[':uid']     = (int) $filtros['usuario_id'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[]            = 'm.creado_en >= :desde';
            $params[':desde']   = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]            = 'm.creado_en <= :hasta';
            $params[':hasta']   = $filtros['fecha_hasta'] . ' 23:59:59';
        }

        $sql = "SELECT
                  m.id,
                  m.tipo,
                  m.cantidad_piezas,
                  m.stock_anterior,
                  m.stock_posterior,
                  m.origen,
                  m.observacion,
                  m.creado_en,
                  p.codigo              AS producto_codigo,
                  p.nombre              AS producto_nombre,
                  p.unidad_medida,
                  p.unidades_por_caja,
                  CONCAT(u.nombre,' ',u.apellidos) AS usuario_nombre
                FROM movimientos m
                JOIN productos p ON p.id = m.producto_id
                JOIN usuarios  u ON u.id = m.usuario_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY m.creado_en DESC";

        return $this->paginate($sql, $params, $pagina, $porPagina);
    }

    /**
     * Totales agregados del período filtrado.
     */
    public function getTotalesMovimientos(array $filtros = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['fecha_desde'])) {
            $where[]          = 'creado_en >= :desde';
            $params[':desde'] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]          = 'creado_en <= :hasta';
            $params[':hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }

        $fila = $this->fetchOne(
            "SELECT
               COUNT(*)                                              AS total_movimientos,
               SUM(CASE WHEN tipo='entrada' THEN cantidad_piezas  ELSE 0 END) AS piezas_entrada,
               SUM(CASE WHEN tipo='salida'  THEN ABS(cantidad_piezas) ELSE 0 END) AS piezas_salida,
               SUM(CASE WHEN tipo='ajuste'  THEN 1 ELSE 0 END)     AS num_ajustes,
               COUNT(DISTINCT producto_id)                          AS productos_distintos,
               COUNT(DISTINCT DATE(creado_en))                      AS dias_con_actividad
             FROM movimientos
             WHERE " . implode(' AND ', $where),
            $params
        );

        return $fila ?: [];
    }

    /**
     * Top 10 productos con más salidas en el período.
     */
    public function getTopProductosSalidas(array $filtros = []): array
    {
        $where  = ["m.tipo IN ('salida','ajuste')"];
        $params = [];

        if (!empty($filtros['fecha_desde'])) {
            $where[]          = 'm.creado_en >= :desde';
            $params[':desde'] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]          = 'm.creado_en <= :hasta';
            $params[':hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }

        return $this->fetchAll(
            "SELECT
               p.codigo,
               p.nombre,
               p.unidad_medida,
               SUM(ABS(m.cantidad_piezas)) AS total_salidas,
               COUNT(m.id)                  AS num_movimientos
             FROM movimientos m
             JOIN productos p ON p.id = m.producto_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY p.id
             ORDER BY total_salidas DESC
             LIMIT 10",
            $params
        );
    }

    /**
     * Actividad diaria para gráfico de barras (rango configurable).
     */
    public function getActividadPorDia(string $fechaDesde, string $fechaHasta): array
    {
        return $this->fetchAll(
            "SELECT
               DATE(creado_en)                                           AS fecha,
               SUM(CASE WHEN tipo='entrada' THEN cantidad_piezas ELSE 0 END)  AS entradas,
               SUM(CASE WHEN tipo='salida'  THEN ABS(cantidad_piezas) ELSE 0 END) AS salidas,
               COUNT(*)                                                  AS total_movimientos
             FROM movimientos
             WHERE creado_en BETWEEN :desde AND :hasta
             GROUP BY DATE(creado_en)
             ORDER BY fecha ASC",
            [
                ':desde' => $fechaDesde . ' 00:00:00',
                ':hasta' => $fechaHasta . ' 23:59:59',
            ]
        );
    }

    // ----------------------------------------------------------------
    // REPORTE: REQUISICIONES
    // ----------------------------------------------------------------

    /**
     * Resumen de requisiciones por estado y período.
     */
    public function getResumenRequisiciones(array $filtros = []): array
    {
        $where  = ['r.eliminado_en IS NULL'];
        $params = [];

        if (!empty($filtros['fecha_desde'])) {
            $where[]          = 'r.fecha_elaboracion >= :desde';
            $params[':desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]          = 'r.fecha_elaboracion <= :hasta';
            $params[':hasta'] = $filtros['fecha_hasta'];
        }

        return $this->fetchAll(
            "SELECT
               r.id, r.folio, r.plantel, r.estado,
               r.total_estimado, r.fecha_elaboracion,
               r.cotizaciones_requeridas,
               CONCAT(us.nombre,' ',us.apellidos) AS solicita_nombre,
               COUNT(ri.id)                        AS num_items
             FROM requisiciones r
             JOIN usuarios us ON us.id = r.solicita_usuario_id
             LEFT JOIN requisicion_items ri ON ri.requisicion_id = r.id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY r.id
             ORDER BY r.fecha_elaboracion DESC",
            $params
        );
    }

    /**
     * KPIs de requisiciones del período.
     */
    public function getKpisRequisiciones(array $filtros = []): array
    {
        $where  = ['eliminado_en IS NULL'];
        $params = [];

        if (!empty($filtros['fecha_desde'])) {
            $where[]          = 'fecha_elaboracion >= :desde';
            $params[':desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]          = 'fecha_elaboracion <= :hasta';
            $params[':hasta'] = $filtros['fecha_hasta'];
        }

        $fila = $this->fetchOne(
            "SELECT
               COUNT(*)                                                         AS total,
               SUM(CASE WHEN estado='autorizada' THEN 1 ELSE 0 END)            AS autorizadas,
               SUM(CASE WHEN estado='rechazada'  THEN 1 ELSE 0 END)            AS rechazadas,
               SUM(CASE WHEN estado='comprada'   THEN 1 ELSE 0 END)            AS compradas,
               SUM(CASE WHEN estado IN('enviada','validada','autorizada') THEN 1 ELSE 0 END) AS en_proceso,
               SUM(CASE WHEN cotizaciones_requeridas=1 THEN 1 ELSE 0 END)      AS con_cotizacion,
               ROUND(SUM(total_estimado), 2)                                   AS monto_total,
               ROUND(AVG(total_estimado), 2)                                   AS monto_promedio
             FROM requisiciones
             WHERE " . implode(' AND ', $where),
            $params
        );

        return $fila ?: [];
    }

    // ----------------------------------------------------------------
    // REPORTE: AUDITORÍA
    // ----------------------------------------------------------------

    /**
     * Bitácora paginada con filtros avanzados.
     * Extiende AuditoriaModel::getFiltered() con más capacidades.
     */
    public function getAuditoriaPaginada(array $filtros = [], int $pagina = 1, int $porPagina = 50): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['usuario_id'])) {
            $where[]              = 'a.usuario_id = :uid';
            $params[':uid']       = (int) $filtros['usuario_id'];
        }
        if (!empty($filtros['modulo'])) {
            $where[]              = 'a.modulo = :modulo';
            $params[':modulo']    = $filtros['modulo'];
        }
        if (!empty($filtros['accion'])) {
            $where[]              = 'a.accion = :accion';
            $params[':accion']    = $filtros['accion'];
        }
        if (!empty($filtros['ip'])) {
            $where[]              = 'a.ip LIKE :ip';
            $params[':ip']        = '%' . $filtros['ip'] . '%';
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[]              = 'a.creado_en >= :desde';
            $params[':desde']     = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]              = 'a.creado_en <= :hasta';
            $params[':hasta']     = $filtros['fecha_hasta'] . ' 23:59:59';
        }

        $sql = "SELECT
                  a.id, a.modulo, a.accion, a.afectado_id,
                  a.descripcion, a.ip, a.user_agent, a.creado_en,
                  CONCAT(u.nombre,' ',u.apellidos) AS usuario_nombre,
                  u.email                          AS usuario_email,
                  u.rol_id
                FROM auditoria a
                LEFT JOIN usuarios u ON u.id = a.usuario_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.creado_en DESC";

        return $this->paginate($sql, $params, $pagina, $porPagina);
    }

    /**
     * Módulos distintos (para el filtro de módulo en auditoría).
     */
    public function getModulosAuditoria(): array
    {
        return $this->fetchAll(
            'SELECT DISTINCT modulo FROM auditoria ORDER BY modulo ASC'
        );
    }

    /**
     * Actividad de auditoría por usuario (top 10 más activos).
     */
    public function getTopUsuariosAuditoria(string $fechaDesde, string $fechaHasta): array
    {
        return $this->fetchAll(
            "SELECT
               CONCAT(u.nombre,' ',u.apellidos) AS usuario_nombre,
               u.email,
               COUNT(a.id)          AS total_acciones,
               MAX(a.creado_en)     AS ultima_accion
             FROM auditoria a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE a.creado_en BETWEEN :desde AND :hasta
             GROUP BY a.usuario_id
             ORDER BY total_acciones DESC
             LIMIT 10",
            [
                ':desde' => $fechaDesde . ' 00:00:00',
                ':hasta' => $fechaHasta . ' 23:59:59',
            ]
        );
    }

    // ----------------------------------------------------------------
    // LISTA DE USUARIOS PARA FILTROS
    // ----------------------------------------------------------------

    public function getUsuariosParaFiltro(): array
    {
        return $this->fetchAll(
            "SELECT id, CONCAT(nombre,' ',apellidos) AS nombre_completo, email
             FROM usuarios WHERE activo = 1 AND eliminado_en IS NULL
             ORDER BY nombre ASC"
        );
    }

    /**
     * Lista de productos para el filtro del reporte de movimientos.
     */
    public function getProductosParaFiltro(): array
    {
        return $this->fetchAll(
            'SELECT id, codigo, nombre FROM productos
             WHERE activo = 1 AND eliminado_en IS NULL
             ORDER BY nombre ASC'
        );
    }
}
