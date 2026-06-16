<?php
/**
 * app/Models/InventarioModel.php
 *
 * Acceso a datos del inventario.
 *
 * PRINCIPIO DE DISEÑO DE ESTE MODEL:
 *   El método moverStock() es el único punto del sistema que
 *   modifica la tabla `stock`. Nadie más puede hacerlo directamente.
 *
 * Toda operación de stock sigue este protocolo atómico:
 *   1. SELECT cantidad_piezas FROM stock FOR UPDATE  (bloqueo de fila)
 *   2. Validar que la operación no deje stock negativo
 *   3. UPDATE stock SET cantidad_piezas = nuevo_valor
 *   4. INSERT INTO movimientos (snapshot antes + después)
 *   → COMMIT si todo OK, ROLLBACK si cualquier paso falla
 */

class InventarioModel extends BaseModel
{
    // ----------------------------------------------------------------
    // OPERACIÓN ATÓMICA DE STOCK (núcleo del módulo)
    // ----------------------------------------------------------------

    /**
     * Mueve el stock de un producto de forma atómica.
     *
     * @param  int    $productoId       ID del producto
     * @param  int    $cantidadPiezas   Positivo=entrada, Negativo=salida/ajuste
     * @param  string $tipo             entrada|salida|ajuste|devolucion
     * @param  string $origen           compra|devolucion|vale_salida|ajuste_manual|inventario_fisico
     * @param  int    $usuarioId        Quién ejecuta el movimiento
     * @param  string $observacion      Nota libre
     * @param  string|null $refTipo     Tabla de referencia (vales, pedidos…)
     * @param  int|null    $refId       ID del documento de referencia
     *
     * @return array  ['stock_anterior' => N, 'stock_posterior' => N, 'movimiento_id' => N]
     * @throws RuntimeException si el stock resultante sería negativo
     * @throws PDOException     si falla la transacción
     */
    public function moverStock(
        int     $productoId,
        int     $cantidadPiezas,
        string  $tipo,
        string  $origen,
        int     $usuarioId,
        string  $observacion = '',
        ?string $refTipo     = null,
        ?int    $refId       = null
    ): array {
        $this->beginTransaction();

        try {
            // ── Paso 1: Leer y bloquear la fila de stock ──
            // SELECT FOR UPDATE previene condiciones de carrera
            // si dos peticiones simultáneas intentan mover el mismo producto.
            $stockFila = $this->fetchOne(
                'SELECT id, cantidad_piezas
                 FROM stock
                 WHERE producto_id = :pid
                 FOR UPDATE',
                [':pid' => $productoId]
            );

            if (!$stockFila) {
                // Crear la fila de stock si no existe (producto sin fila inicial)
                $this->insert(
                    'INSERT INTO stock (producto_id, cantidad_piezas) VALUES (:pid, 0)',
                    [':pid' => $productoId]
                );
                $stockFila = ['id' => null, 'cantidad_piezas' => 0];
            }

            $anterior = (int) $stockFila['cantidad_piezas'];
            $posterior = $anterior + $cantidadPiezas;

            // ── Paso 2: Validar que no quede negativo ──
            // La BD también tiene CHECK, pero validamos aquí para dar
            // un mensaje de error claro al usuario antes del intento.
            if ($posterior < 0) {
                throw new RuntimeException(
                    "Stock insuficiente. Disponible: {$anterior} pz. " .
                    "Solicitado: " . abs($cantidadPiezas) . " pz."
                );
            }

            // ── Paso 3: Actualizar stock ──
            $campoFecha = $cantidadPiezas > 0 ? 'ultima_entrada' : 'ultima_salida';
            $this->execute(
                "UPDATE stock
                 SET cantidad_piezas = :nuevo, {$campoFecha} = NOW()
                 WHERE producto_id = :pid",
                [':nuevo' => $posterior, ':pid' => $productoId]
            );

            // ── Paso 4: Registrar movimiento inmutable ──
            $movId = $this->insert(
                'INSERT INTO movimientos
                   (producto_id, tipo, cantidad_piezas,
                    stock_anterior, stock_posterior,
                    origen, referencia_tipo, referencia_id,
                    observacion, usuario_id)
                 VALUES
                   (:pid, :tipo, :cantidad,
                    :anterior, :posterior,
                    :origen, :ref_tipo, :ref_id,
                    :obs, :uid)',
                [
                    ':pid'      => $productoId,
                    ':tipo'     => $tipo,
                    ':cantidad' => $cantidadPiezas,
                    ':anterior' => $anterior,
                    ':posterior'=> $posterior,
                    ':origen'   => $origen,
                    ':ref_tipo' => $refTipo,
                    ':ref_id'   => $refId,
                    ':obs'      => $observacion ?: null,
                    ':uid'      => $usuarioId,
                ]
            );

            $this->commit();

            return [
                'stock_anterior'  => $anterior,
                'stock_posterior' => $posterior,
                'movimiento_id'   => $movId,
            ];

        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // MOVIMIENTO EN TRANSACCIÓN EXTERNA (usado por ValeModel)
    // ----------------------------------------------------------------

    /**
     * Versión de moverStock que participa en una transacción ya activa.
     * NO inicia ni cierra transacción propia — el llamador es responsable.
     *
     * Usa la misma conexión PDO inyectada para que los SAVEPOINT y FOR UPDATE
     * operen dentro de la misma transacción del vale.
     *
     * @param PDO $conexion  Conexión activa con transacción ya iniciada
     */
    public function moverStockEnTransaccion(
        int     $productoId,
        int     $cantidadPiezas,
        string  $tipo,
        string  $origen,
        int     $usuarioId,
        string  $observacion = '',
        ?string $refTipo     = null,
        ?int    $refId       = null,
        ?PDO    $conexion    = null
    ): array {
        // Usar la conexión inyectada si se proporciona, si no la propia
        $db = $conexion ?? $this->db;

        // Leer y bloquear la fila de stock dentro de la transacción activa
        $stmt = $db->prepare(
            'SELECT id, cantidad_piezas FROM stock WHERE producto_id = :pid FOR UPDATE'
        );
        $stmt->execute([':pid' => $productoId]);
        $stockFila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stockFila) {
            throw new RuntimeException(
                "No existe fila de stock para el producto ID {$productoId}."
            );
        }

        $anterior  = (int) $stockFila['cantidad_piezas'];
        $posterior = $anterior + $cantidadPiezas;

        if ($posterior < 0) {
            throw new RuntimeException(
                "Stock insuficiente. Disponible: {$anterior} pz. " .
                "Solicitado: " . abs($cantidadPiezas) . " pz."
            );
        }

        // Actualizar stock
        $campoFecha = $cantidadPiezas > 0 ? 'ultima_entrada' : 'ultima_salida';
        $stmt = $db->prepare(
            "UPDATE stock SET cantidad_piezas = :nuevo, {$campoFecha} = NOW()
             WHERE producto_id = :pid"
        );
        $stmt->execute([':nuevo' => $posterior, ':pid' => $productoId]);

        // Registrar movimiento
        $stmt = $db->prepare(
            'INSERT INTO movimientos
               (producto_id, tipo, cantidad_piezas, stock_anterior, stock_posterior,
                origen, referencia_tipo, referencia_id, observacion, usuario_id)
             VALUES
               (:pid, :tipo, :cant, :ant, :post,
                :origen, :ref_tipo, :ref_id, :obs, :uid)'
        );
        $stmt->execute([
            ':pid'      => $productoId,
            ':tipo'     => $tipo,
            ':cant'     => $cantidadPiezas,
            ':ant'      => $anterior,
            ':post'     => $posterior,
            ':origen'   => $origen,
            ':ref_tipo' => $refTipo,
            ':ref_id'   => $refId,
            ':obs'      => $observacion ?: null,
            ':uid'      => $usuarioId,
        ]);

        $movId = (int) $db->lastInsertId();

        return [
            'stock_anterior'  => $anterior,
            'stock_posterior' => $posterior,
            'movimiento_id'   => $movId,
        ];
    }

    // ----------------------------------------------------------------
    // AJUSTE DE INVENTARIO FÍSICO
    // ----------------------------------------------------------------

    /**
     * Ajusta el stock a un valor absoluto (inventario físico).
     * Calcula automáticamente la diferencia y la dirección.
     *
     * @param  int    $productoId    ID del producto
     * @param  int    $nuevoValor    Stock real contado en físico (piezas)
     * @param  int    $usuarioId
     * @param  string $observacion
     *
     * @return array  Resultado de moverStock()
     */
    public function ajustarStock(
        int    $productoId,
        int    $nuevoValor,
        int    $usuarioId,
        string $observacion = 'Ajuste por inventario físico'
    ): array {
        // Leer el stock actual SIN bloqueo (solo lectura previa para calcular diff)
        $fila = $this->fetchOne(
            'SELECT cantidad_piezas FROM stock WHERE producto_id = :pid',
            [':pid' => $productoId]
        );

        $actual     = (int) ($fila['cantidad_piezas'] ?? 0);
        $diferencia = $nuevoValor - $actual;

        if ($diferencia === 0) {
            // Nada que ajustar — registrar igualmente para dejar rastro
            return $this->moverStock(
                $productoId, 0, 'ajuste', 'inventario_fisico',
                $usuarioId, 'Sin diferencia — ' . $observacion
            );
        }

        $tipo = $diferencia > 0 ? 'entrada' : 'ajuste';

        return $this->moverStock(
            $productoId, $diferencia, $tipo, 'inventario_fisico',
            $usuarioId, $observacion
        );
    }

    // ----------------------------------------------------------------
    // LECTURAS
    // ----------------------------------------------------------------

    /**
     * Stock actual de un producto (lectura directa, sin bloqueo).
     */
    public function getStock(int $productoId): int
    {
        $fila = $this->fetchOne(
            'SELECT cantidad_piezas FROM stock WHERE producto_id = :pid',
            [':pid' => $productoId]
        );
        return (int) ($fila['cantidad_piezas'] ?? 0);
    }

    /**
     * Historial de movimientos con filtros y paginación.
     */
    public function getMovimientos(array $filtros = [], int $pagina = 1, int $porPagina = 25): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['producto_id'])) {
            $where[]              = 'm.producto_id = :pid';
            $params[':pid']       = (int) $filtros['producto_id'];
        }
        if (!empty($filtros['tipo'])) {
            $where[]              = 'm.tipo = :tipo';
            $params[':tipo']      = $filtros['tipo'];
        }
        if (!empty($filtros['origen'])) {
            $where[]              = 'm.origen = :origen';
            $params[':origen']    = $filtros['origen'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[]              = 'm.creado_en >= :desde';
            $params[':desde']     = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]              = 'm.creado_en <= :hasta';
            $params[':hasta']     = $filtros['fecha_hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['usuario_id'])) {
            $where[]              = 'm.usuario_id = :uid';
            $params[':uid']       = (int) $filtros['usuario_id'];
        }

        $sql = 'SELECT
                  m.id,
                  m.tipo,
                  m.cantidad_piezas,
                  m.stock_anterior,
                  m.stock_posterior,
                  m.origen,
                  m.referencia_tipo,
                  m.referencia_id,
                  m.observacion,
                  m.creado_en,
                  p.nombre    AS producto_nombre,
                  p.codigo    AS producto_codigo,
                  p.unidad_medida,
                  p.unidades_por_caja,
                  CONCAT(u.nombre, " ", u.apellidos) AS usuario_nombre
                FROM movimientos m
                JOIN productos p ON p.id = m.producto_id
                JOIN usuarios  u ON u.id = m.usuario_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY m.creado_en DESC';

        return $this->paginate($sql, $params, $pagina, $porPagina);
    }

    /**
     * Resumen de entradas del día actual (para el formulario).
     */
    public function getEntradasHoy(): array
    {
        return $this->fetchAll(
            'SELECT
               m.id,
               m.cantidad_piezas,
               m.origen,
               m.observacion,
               m.creado_en,
               p.nombre  AS producto_nombre,
               p.codigo  AS producto_codigo,
               p.unidades_por_caja,
               p.unidad_medida,
               CONCAT(u.nombre, " ", u.apellidos) AS usuario_nombre
             FROM movimientos m
             JOIN productos p ON p.id = m.producto_id
             JOIN usuarios  u ON u.id = m.usuario_id
             WHERE m.tipo = "entrada"
               AND DATE(m.creado_en) = CURDATE()
             ORDER BY m.creado_en DESC
             LIMIT 15'
        );
    }

    /**
     * Resumen de salidas del día actual.
     */
    public function getSalidasHoy(): array
    {
        return $this->fetchAll(
            'SELECT
               m.id,
               m.cantidad_piezas,
               m.origen,
               m.observacion,
               m.creado_en,
               p.nombre  AS producto_nombre,
               p.codigo  AS producto_codigo,
               p.unidades_por_caja,
               p.unidad_medida,
               CONCAT(u.nombre, " ", u.apellidos) AS usuario_nombre
             FROM movimientos m
             JOIN productos p ON p.id = m.producto_id
             JOIN usuarios  u ON u.id = m.usuario_id
             WHERE m.tipo IN ("salida", "ajuste")
               AND DATE(m.creado_en) = CURDATE()
             ORDER BY m.creado_en DESC
             LIMIT 15'
        );
    }

    /**
     * Totales del día para la cabecera del módulo.
     */
    public function getTotalesHoy(): array
    {
        $fila = $this->fetchOne(
            'SELECT
               SUM(CASE WHEN tipo = "entrada"              THEN cantidad_piezas ELSE 0 END) AS piezas_entradas,
               SUM(CASE WHEN tipo IN ("salida","ajuste")   THEN ABS(cantidad_piezas) ELSE 0 END) AS piezas_salidas,
               COUNT(CASE WHEN tipo = "entrada"            THEN 1 END) AS num_entradas,
               COUNT(CASE WHEN tipo IN ("salida","ajuste") THEN 1 END) AS num_salidas
             FROM movimientos
             WHERE DATE(creado_en) = CURDATE()'
        );

        return [
            'piezas_entradas' => (int) ($fila['piezas_entradas'] ?? 0),
            'piezas_salidas'  => (int) ($fila['piezas_salidas']  ?? 0),
            'num_entradas'    => (int) ($fila['num_entradas']     ?? 0),
            'num_salidas'     => (int) ($fila['num_salidas']      ?? 0),
        ];
    }
}
