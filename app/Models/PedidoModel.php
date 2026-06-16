<?php
/**
 * app/Models/PedidoModel.php
 *
 * Consultas de base de datos para Pedidos y Pedido_Items.
 *
 * REGLA CRÍTICA — hardcoded en este modelo:
 *   Pedido NO descuenta stock.
 *   Este modelo nunca llama a InventarioModel ni toca la tabla `stock`.
 *   El stock baja EXCLUSIVAMENTE cuando se emite un vale de salida (Fase H).
 */

class PedidoModel extends BaseModel
{
    // ----------------------------------------------------------------
    // FOLIO — generación atómica
    // ----------------------------------------------------------------

    /**
     * Genera el siguiente folio correlativo dentro de una transacción.
     * Formato: PED-YYYY-NNNNN
     *
     * DEBE llamarse dentro de una transacción activa con FOR UPDATE
     * para garantizar unicidad bajo concurrencia.
     */
    private function generarFolio(): string
    {
        $anio = date('Y');
        // Bloquear la última fila del año para evitar race condition
        $ultima = $this->fetchOne(
            "SELECT folio FROM pedidos
             WHERE folio LIKE :patron
             ORDER BY id DESC LIMIT 1
             FOR UPDATE",
            [':patron' => "PED-{$anio}-%"]
        );

        $siguiente = 1;
        if ($ultima) {
            $partes    = explode('-', $ultima['folio']);
            $siguiente = ((int) end($partes)) + 1;
        }

        return sprintf('PED-%s-%05d', $anio, $siguiente);
    }

    // ----------------------------------------------------------------
    // LECTURA
    // ----------------------------------------------------------------

    /**
     * Lista paginada de pedidos con filtros.
     */
    public function getPaginados(array $filtros = [], int $pagina = 1, int $porPagina = 25): array
    {
        $where  = ['p.eliminado_en IS NULL'];
        $params = [];

        if (!empty($filtros['estado'])) {
            $where[]            = 'p.estado = :estado';
            $params[':estado']  = $filtros['estado'];
        }
        if (!empty($filtros['prioridad'])) {
            $where[]              = 'p.prioridad = :prioridad';
            $params[':prioridad'] = $filtros['prioridad'];
        }
        if (!empty($filtros['solicitante_id'])) {
            $where[]              = 'p.solicitante_id = :sol_id';
            $params[':sol_id']    = (int) $filtros['solicitante_id'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[]              = 'p.creado_en >= :desde';
            $params[':desde']     = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]              = 'p.creado_en <= :hasta';
            $params[':hasta']     = $filtros['fecha_hasta'] . ' 23:59:59';
        }
        // Solo ver los propios (rol Usuario)
        if (!empty($filtros['solo_propios'])) {
            $where[]              = 'p.solicitante_id = :uid_prop';
            $params[':uid_prop']  = (int) $filtros['solo_propios'];
        }

        $sql = "SELECT
                  p.id, p.folio, p.estado, p.prioridad, p.plantel,
                  p.fecha_requerida, p.creado_en, p.observaciones,
                  CONCAT(u.nombre, ' ', u.apellidos) AS solicitante,
                  CONCAT(ua.nombre, ' ', ua.apellidos) AS almacenista,
                  COUNT(pi.id)                       AS total_items,
                  SUM(pi.cantidad_piezas)            AS total_piezas_sol,
                  SUM(pi.cantidad_entregada_piezas)  AS total_piezas_ent
                FROM pedidos p
                JOIN usuarios u     ON u.id  = p.solicitante_id
                LEFT JOIN usuarios ua ON ua.id = p.atendido_por
                LEFT JOIN pedido_items pi ON pi.pedido_id = p.id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY p.id
                ORDER BY
                  FIELD(p.prioridad, 'urgente', 'normal'),
                  FIELD(p.estado, 'pendiente', 'en_proceso', 'entregado_parcial', 'entregado', 'cancelado'),
                  p.creado_en DESC";

        return $this->paginate($sql, $params, $pagina, $porPagina);
    }

    /**
     * Detalle completo de un pedido con todos sus ítems.
     */
    public function findById(int $id): array|false
    {
        $pedido = $this->fetchOne(
            "SELECT p.*,
                    CONCAT(u.nombre,  ' ', u.apellidos)  AS solicitante_nombre,
                    u.email                               AS solicitante_email,
                    CONCAT(ua.nombre, ' ', ua.apellidos) AS almacenista_nombre,
                    e.nombre                              AS empleado_nombre,
                    e.apellidos                           AS empleado_apellidos,
                    e.puesto                              AS empleado_puesto,
                    e.plantel                             AS empleado_plantel
             FROM pedidos p
             JOIN usuarios u      ON u.id  = p.solicitante_id
             LEFT JOIN usuarios ua ON ua.id = p.atendido_por
             LEFT JOIN empleados e ON e.id  = p.empleado_id
             WHERE p.id = :id AND p.eliminado_en IS NULL LIMIT 1",
            [':id' => $id]
        );

        if (!$pedido) return false;

        // Cargar ítems con datos del producto
        $pedido['items'] = $this->fetchAll(
            "SELECT pi.*,
                    p.codigo, p.nombre AS producto_nombre,
                    p.unidad_medida, p.unidades_por_caja,
                    COALESCE(s.cantidad_piezas, 0) AS stock_actual
             FROM pedido_items pi
             JOIN productos p ON p.id = pi.producto_id
             LEFT JOIN stock s ON s.producto_id = p.id
             WHERE pi.pedido_id = :pid
             ORDER BY pi.id ASC",
            [':pid' => $id]
        );

        return $pedido;
    }

    /**
     * Conteo de pedidos por estado (para badges del sidebar).
     */
    public function contarPorEstado(): array
    {
        $filas = $this->fetchAll(
            'SELECT estado, COUNT(*) AS total
             FROM pedidos
             WHERE eliminado_en IS NULL
             GROUP BY estado'
        );

        $result = [];
        foreach ($filas as $f) {
            $result[$f['estado']] = (int) $f['total'];
        }
        return $result;
    }

    // ----------------------------------------------------------------
    // ESCRITURA
    // ----------------------------------------------------------------

    /**
     * Crea un pedido con sus ítems en una sola transacción.
     * Asigna el folio automáticamente de forma atómica.
     *
     * @param  array $cabecera  Datos del pedido (sin folio)
     * @param  array $items     [{producto_id, cantidad_piezas, observacion}]
     * @return int  ID del pedido creado
     */
    public function create(array $cabecera, array $items): int
    {
        $this->beginTransaction();

        try {
            $folio = $this->generarFolio();

            $pedidoId = $this->insert(
                'INSERT INTO pedidos
                   (folio, solicitante_id, empleado_id, plantel,
                    prioridad, observaciones, fecha_requerida)
                 VALUES
                   (:folio, :sol_id, :emp_id, :plantel,
                    :prioridad, :obs, :fecha_req)',
                [
                    ':folio'     => $folio,
                    ':sol_id'    => $cabecera['solicitante_id'],
                    ':emp_id'    => $cabecera['empleado_id']   ?? null,
                    ':plantel'   => $cabecera['plantel']        ?? null,
                    ':prioridad' => $cabecera['prioridad']      ?? 'normal',
                    ':obs'       => $cabecera['observaciones']  ?? null,
                    ':fecha_req' => $cabecera['fecha_requerida'] ?? null,
                ]
            );

            foreach ($items as $item) {
                $this->insert(
                    'INSERT INTO pedido_items
                       (pedido_id, producto_id, cantidad_piezas, observacion)
                     VALUES (:pid, :prod_id, :cant, :obs)',
                    [
                        ':pid'     => $pedidoId,
                        ':prod_id' => (int) $item['producto_id'],
                        ':cant'    => (int) $item['cantidad_piezas'],
                        ':obs'     => $item['observacion'] ?? null,
                    ]
                );
            }

            $this->commit();
            return $pedidoId;

        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Actualiza el estado del pedido y registra quién lo atendió.
     */
    public function actualizarEstado(int $id, string $estado, ?int $almacenistaId = null): void
    {
        $this->execute(
            'UPDATE pedidos
             SET estado = :estado,
                 atendido_por   = COALESCE(:alm, atendido_por),
                 fecha_atencion = CASE WHEN :alm2 IS NOT NULL THEN NOW() ELSE fecha_atencion END
             WHERE id = :id',
            [
                ':estado' => $estado,
                ':alm'    => $almacenistaId,
                ':alm2'   => $almacenistaId,
                ':id'     => $id,
            ]
        );
    }

    /**
     * Registra la entrega (parcial o total) de un ítem del pedido.
     * NO toca stock — solo actualiza pedido_items.
     */
    public function registrarEntregaItem(
        int $itemId,
        int $cantidadEntregadaPiezas
    ): void {
        // Determinar nuevo estado del ítem
        $item = $this->fetchOne(
            'SELECT cantidad_piezas, cantidad_entregada_piezas
             FROM pedido_items WHERE id = :id',
            [':id' => $itemId]
        );

        if (!$item) return;

        $totalEntregado = $item['cantidad_entregada_piezas'] + $cantidadEntregadaPiezas;
        $estadoItem = $totalEntregado >= $item['cantidad_piezas']
            ? 'entregado' : 'parcial';

        $this->execute(
            'UPDATE pedido_items
             SET cantidad_entregada_piezas = :total, estado_item = :estado
             WHERE id = :id',
            [
                ':total'  => $totalEntregado,
                ':estado' => $estadoItem,
                ':id'     => $itemId,
            ]
        );
    }

    /**
     * Cancela todos los ítems pendientes de un pedido.
     */
    public function cancelarItemsPendientes(int $pedidoId): void
    {
        $this->execute(
            "UPDATE pedido_items
             SET estado_item = 'cancelado'
             WHERE pedido_id = :pid
               AND estado_item IN ('pendiente', 'parcial')",
            [':pid' => $pedidoId]
        );
    }

    /**
     * Soft delete de un pedido (solo si está cancelado o pendiente).
     */
    public function softDelete(int $id): void
    {
        $this->execute(
            'UPDATE pedidos SET eliminado_en = NOW() WHERE id = :id',
            [':id' => $id]
        );
    }
}
