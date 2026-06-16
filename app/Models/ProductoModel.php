<?php
/**
 * app/Models/ProductoModel.php
 *
 * Consultas de base de datos para Productos.
 * Utiliza la vista v_stock_productos para lecturas con stock.
 *
 * REGLA CRÍTICA: stock siempre en PIEZAS BASE.
 * La traducción visual (cajas + piezas) la hace ProductoService.
 */

class ProductoModel extends BaseModel
{
    // ----------------------------------------------------------------
    // LECTURA
    // ----------------------------------------------------------------

    /**
     * Lista paginada de productos con stock para DataTables.
     * Usa la vista v_stock_productos que ya trae cajas y piezas calculadas.
     */
    public function getPaginados(
        string $busqueda    = '',
        int    $categoriaId = 0,
        string $estado      = '',
        int    $pagina      = 1,
        int    $porPagina   = 25
    ): array {
        $where  = ['p.activo = 1', 'p.eliminado_en IS NULL'];
        $params = [];

        if ($busqueda !== '') {
            $where[]              = '(p.nombre LIKE :busqueda OR p.codigo LIKE :busqueda2)';
            $params[':busqueda']  = "%{$busqueda}%";
            $params[':busqueda2'] = "%{$busqueda}%";
        }
        if ($categoriaId > 0) {
            $where[]           = 'p.categoria_id = :cat_id';
            $params[':cat_id'] = $categoriaId;
        }
        if ($estado !== '') {
            $estadoWhere = match($estado) {
                'sin_stock' => 'COALESCE(s.cantidad_piezas, 0) = 0',
                'critico'   => 'COALESCE(s.cantidad_piezas, 0) > 0 AND COALESCE(s.cantidad_piezas, 0) <= p.stock_minimo',
                'bajo'      => 'COALESCE(s.cantidad_piezas, 0) > p.stock_minimo AND COALESCE(s.cantidad_piezas, 0) <= (p.stock_minimo * 1.5)',
                'ok'        => 'COALESCE(s.cantidad_piezas, 0) > (p.stock_minimo * 1.5)',
                default     => '1=1',
            };
            $where[] = $estadoWhere;
        }

        $sql = "SELECT
                  p.id                                                           AS producto_id,
                  p.id,
                  p.codigo,
                  p.nombre,
                  p.descripcion,
                  p.categoria_id,
                  p.unidad_medida,
                  p.presentacion,
                  p.unidades_por_caja,
                  p.precio_unitario,
                  p.stock_minimo,
                  p.activo,
                  c.nombre                                                       AS categoria_nombre,
                  COALESCE(s.cantidad_piezas, 0)                                AS cantidad_piezas,
                  FLOOR(COALESCE(s.cantidad_piezas,0) / p.unidades_por_caja)   AS cajas_completas,
                  MOD(COALESCE(s.cantidad_piezas,0), p.unidades_por_caja)      AS piezas_sueltas,
                  CASE
                    WHEN COALESCE(s.cantidad_piezas,0) = 0                     THEN 'sin_stock'
                    WHEN COALESCE(s.cantidad_piezas,0) <= p.stock_minimo       THEN 'critico'
                    WHEN COALESCE(s.cantidad_piezas,0) <= (p.stock_minimo*1.5) THEN 'bajo'
                    ELSE 'ok'
                  END AS estado_stock
                FROM productos p
                LEFT JOIN categorias c ON c.id = p.categoria_id
                LEFT JOIN stock      s ON s.producto_id = p.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.nombre ASC";

        return $this->paginate($sql, $params, $pagina, $porPagina);
    }

    /**
     * Todos los productos activos para selects (pedidos, vales, etc.).
     */
    public function getParaSelect(): array
    {
        return $this->fetchAll(
            'SELECT p.id, p.codigo, p.nombre, p.unidad_medida,
                    p.unidades_por_caja, p.precio_unitario,
                    COALESCE(s.cantidad_piezas, 0) AS stock_actual
             FROM productos p
             LEFT JOIN stock s ON s.producto_id = p.id
             WHERE p.activo = 1 AND p.eliminado_en IS NULL
             ORDER BY p.nombre ASC'
        );
    }

    /**
     * Detalle completo de un producto con su stock actual.
     */
    public function findById(int $id): array|false
    {
        return $this->fetchOne(
            'SELECT p.*,
                    c.nombre AS categoria_nombre,
                    COALESCE(s.cantidad_piezas, 0) AS stock_actual,
                    FLOOR(COALESCE(s.cantidad_piezas,0) / p.unidades_por_caja) AS cajas_completas,
                    MOD(COALESCE(s.cantidad_piezas,0),  p.unidades_por_caja)   AS piezas_sueltas
             FROM productos p
             LEFT JOIN categorias c ON c.id = p.categoria_id
             LEFT JOIN stock      s ON s.producto_id = p.id
             WHERE p.id = :id AND p.eliminado_en IS NULL
             LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Busca producto por código (para validar duplicados).
     */
    public function findByCodigo(string $codigo, ?int $excludeId = null): array|false
    {
        $sql    = 'SELECT id FROM productos WHERE codigo = :codigo AND eliminado_en IS NULL';
        $params = [':codigo' => $codigo];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude';
            $params[':exclude'] = $excludeId;
        }

        return $this->fetchOne($sql . ' LIMIT 1', $params);
    }

    /**
     * Verifica si un producto tiene movimientos de stock.
     * Usado antes de eliminar.
     */
    public function tieneMov(int $id): bool
    {
        return $this->count(
            'SELECT COUNT(*) FROM movimientos WHERE producto_id = :id',
            [':id' => $id]
        ) > 0;
    }

    // ----------------------------------------------------------------
    // ESCRITURA
    // ----------------------------------------------------------------

    /**
     * Crea el producto y su fila de stock vacía en una transacción.
     */
    public function create(array $data): int
    {
        $this->beginTransaction();
        try {
            $id = $this->insert(
                'INSERT INTO productos
                   (codigo, nombre, descripcion, categoria_id, unidad_medida,
                    presentacion, unidades_por_caja, precio_unitario,
                    stock_minimo, creado_por)
                 VALUES
                   (:codigo, :nombre, :descripcion, :categoria_id, :unidad_medida,
                    :presentacion, :unidades_por_caja, :precio_unitario,
                    :stock_minimo, :creado_por)',
                [
                    ':codigo'           => $data['codigo'],
                    ':nombre'           => $data['nombre'],
                    ':descripcion'      => $data['descripcion']      ?? null,
                    ':categoria_id'     => $data['categoria_id'],
                    ':unidad_medida'    => $data['unidad_medida'],
                    ':presentacion'     => $data['presentacion']     ?? null,
                    ':unidades_por_caja'=> $data['unidades_por_caja'],
                    ':precio_unitario'  => $data['precio_unitario'],
                    ':stock_minimo'     => $data['stock_minimo'],
                    ':creado_por'       => $data['creado_por'],
                ]
            );

            // Crear fila de stock vacía (cantidad = 0)
            $this->insert(
                'INSERT INTO stock (producto_id, cantidad_piezas) VALUES (:pid, 0)',
                [':pid' => $id]
            );

            $this->commit();
            return $id;

        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function update(int $id, array $data): void
    {
        $this->execute(
            'UPDATE productos SET
               codigo = :codigo, nombre = :nombre, descripcion = :descripcion,
               categoria_id = :categoria_id, unidad_medida = :unidad_medida,
               presentacion = :presentacion, unidades_por_caja = :unidades_por_caja,
               precio_unitario = :precio_unitario, stock_minimo = :stock_minimo,
               activo = :activo
             WHERE id = :id',
            [
                ':codigo'            => $data['codigo'],
                ':nombre'            => $data['nombre'],
                ':descripcion'       => $data['descripcion']      ?? null,
                ':categoria_id'      => $data['categoria_id'],
                ':unidad_medida'     => $data['unidad_medida'],
                ':presentacion'      => $data['presentacion']     ?? null,
                ':unidades_por_caja' => $data['unidades_por_caja'],
                ':precio_unitario'   => $data['precio_unitario'],
                ':stock_minimo'      => $data['stock_minimo'],
                ':activo'            => (int) ($data['activo'] ?? 1),
                ':id'                => $id,
            ]
        );
    }

    /**
     * Guarda la ruta del QR generado.
     */
    public function updateQr(int $id, string $rutaQr): void
    {
        $this->execute(
            'UPDATE productos SET codigo_qr = :qr WHERE id = :id',
            [':qr' => $rutaQr, ':id' => $id]
        );
    }

    public function softDelete(int $id): void
    {
        $this->execute(
            'UPDATE productos SET eliminado_en = NOW(), activo = 0 WHERE id = :id',
            [':id' => $id]
        );
    }
}
