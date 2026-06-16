<?php
/**
 * app/Models/ValeModel.php
 *
 * Acceso a datos de Vales de Salida y Resguardo.
 *
 * ARQUITECTURA DEL DESCUENTO:
 *   El campo stock_descontado = 0|1 es el guardián del doble descuento.
 *   La secuencia atómica completa ocurre en ValeService::emitir():
 *
 *   BEGIN TRANSACTION
 *     SELECT stock_descontado FOR UPDATE  → si = 1, ROLLBACK
 *     FOREACH item → InventarioModel::moverStock(negativo)
 *     UPDATE vales SET stock_descontado = 1
 *   COMMIT
 *
 *   Este modelo solo provee acceso a datos.
 */

class ValeModel extends BaseModel
{
    // ----------------------------------------------------------------
    // FOLIO — generación atómica
    // ----------------------------------------------------------------

    /**
     * Genera el siguiente folio dentro de una transacción activa.
     * Formato: VS-YYYY-NNNNN (salida) | VR-YYYY-NNNNN (resguardo)
     */
    public function generarFolio(string $tipo): string
    {
        $prefijo = $tipo === 'salida' ? 'VS' : 'VR';
        $anio    = date('Y');

        $ultima = $this->fetchOne(
            "SELECT folio FROM vales
             WHERE folio LIKE :patron
             ORDER BY id DESC LIMIT 1
             FOR UPDATE",
            [':patron' => "{$prefijo}-{$anio}-%"]
        );

        $siguiente = 1;
        if ($ultima) {
            $partes    = explode('-', $ultima['folio']);
            $siguiente = ((int) end($partes)) + 1;
        }

        return sprintf('%s-%s-%05d', $prefijo, $anio, $siguiente);
    }

    // ----------------------------------------------------------------
    // LECTURA
    // ----------------------------------------------------------------

    public function getPaginados(array $filtros = [], int $pagina = 1, int $porPagina = 25): array
    {
        $where  = ['v.eliminado_en IS NULL'];
        $params = [];

        if (!empty($filtros['tipo'])) {
            $where[]         = 'v.tipo = :tipo';
            $params[':tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['estado'])) {
            $where[]            = 'v.estado = :estado';
            $params[':estado']  = $filtros['estado'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[]            = 'v.fecha_emision >= :desde';
            $params[':desde']   = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]            = 'v.fecha_emision <= :hasta';
            $params[':hasta']   = $filtros['fecha_hasta'];
        }

        $sql = "SELECT
                  v.id, v.folio, v.tipo, v.estado,
                  v.referencia, v.plantel, v.fecha_emision,
                  v.stock_descontado, v.creado_en,
                  CONCAT(COALESCE(e.nombre,''), ' ', COALESCE(e.apellidos,'')) AS empleado_nombre,
                  CONCAT(a.nombre, ' ', a.apellidos) AS autorizo_nombre,
                  COUNT(vi.id)    AS total_items,
                  SUM(vi.importe) AS importe_total
                FROM vales v
                LEFT JOIN empleados  e  ON e.id  = v.empleado_id
                JOIN  usuarios   a  ON a.id  = v.autorizo_id
                LEFT JOIN vale_items vi ON vi.vale_id = v.id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY v.id
                ORDER BY v.fecha_emision DESC, v.id DESC";

        return $this->paginate($sql, $params, $pagina, $porPagina);
    }

    public function findById(int $id): array|false
    {
        $vale = $this->fetchOne(
            "SELECT v.*,
                    CONCAT(COALESCE(e.nombre,''), ' ', COALESCE(e.apellidos,'')) AS empleado_nombre,
                    e.puesto           AS empleado_puesto,
                    e.numero_empleado,
                    CONCAT(a.nombre, ' ', a.apellidos) AS autorizo_nombre
             FROM vales v
             LEFT JOIN empleados e ON e.id = v.empleado_id
             JOIN  usuarios  a ON a.id = v.autorizo_id
             WHERE v.id = :id AND v.eliminado_en IS NULL LIMIT 1",
            [':id' => $id]
        );

        if (!$vale) return false;

        $vale['items'] = $this->fetchAll(
            "SELECT vi.*,
                    p.codigo, p.nombre AS producto_nombre,
                    p.unidad_medida, p.unidades_por_caja
             FROM vale_items vi
             JOIN productos p ON p.id = vi.producto_id
             WHERE vi.vale_id = :vid ORDER BY vi.id ASC",
            [':vid' => $id]
        );

        return $vale;
    }

    /**
     * Lee stock_descontado con bloqueo FOR UPDATE.
     * Llamado desde ValeService dentro de una transacción activa.
     */
    public function leerParaDescuento(int $valeId): array|false
    {
        return $this->fetchOne(
            'SELECT id, stock_descontado, tipo, estado
             FROM vales WHERE id = :id FOR UPDATE',
            [':id' => $valeId]
        );
    }

    // ----------------------------------------------------------------
    // ESCRITURA
    // ----------------------------------------------------------------

    public function create(array $cabecera, array $items): int
    {
        $this->beginTransaction();

        try {
            $folio  = $this->generarFolio($cabecera['tipo']);

            $valeId = $this->insert(
                'INSERT INTO vales
                   (folio, tipo, referencia, plantel, empleado_id,
                    pedido_id, requisicion_id, autorizo_id,
                    fecha_emision, observaciones)
                 VALUES
                   (:folio,:tipo,:ref,:plantel,:emp_id,
                    :ped_id,:req_id,:aut_id,
                    :fecha,:obs)',
                [
                    ':folio'   => $folio,
                    ':tipo'    => $cabecera['tipo'],
                    ':ref'     => $cabecera['referencia']     ?? null,
                    ':plantel' => $cabecera['plantel']         ?? null,
                    ':emp_id'  => $cabecera['empleado_id']    ?? null,
                    ':ped_id'  => $cabecera['pedido_id']      ?? null,
                    ':req_id'  => $cabecera['requisicion_id'] ?? null,
                    ':aut_id'  => $cabecera['autorizo_id'],
                    ':fecha'   => $cabecera['fecha_emision']  ?? date('Y-m-d'),
                    ':obs'     => $cabecera['observaciones']  ?? null,
                ]
            );

            foreach ($items as $item) {
                $importe = round(
                    (float) $item['precio_unitario'] * (int) $item['cantidad_piezas'], 2
                );
                $this->insert(
                    'INSERT INTO vale_items
                       (vale_id, producto_id, cantidad_piezas,
                        precio_unitario, importe, descripcion_item)
                     VALUES (:vid,:pid,:cant,:precio,:importe,:desc)',
                    [
                        ':vid'     => $valeId,
                        ':pid'     => (int)   $item['producto_id'],
                        ':cant'    => (int)   $item['cantidad_piezas'],
                        ':precio'  => (float) $item['precio_unitario'],
                        ':importe' => $importe,
                        ':desc'    => $item['descripcion_item'] ?? null,
                    ]
                );
            }

            $this->commit();
            return $valeId;

        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Marca el vale como emitido y activa stock_descontado = 1.
     * Solo llamado desde ValeService::emitir() dentro de su transacción.
     */
    public function marcarEmitido(int $id, ?string $recibioNombre = null): void
    {
        $this->execute(
            "UPDATE vales
             SET estado = 'emitido',
                 stock_descontado = 1,
                 fecha_entrega    = NOW(),
                 recibio_nombre   = :recibio
             WHERE id = :id",
            [':recibio' => $recibioNombre, ':id' => $id]
        );
    }

    public function actualizarPdf(int $id, string $rutaPdf): void
    {
        $this->execute(
            'UPDATE vales SET pdf_path = :ruta WHERE id = :id',
            [':ruta' => $rutaPdf, ':id' => $id]
        );
    }

    /**
     * Solo puede cancelarse si stock_descontado = 0.
     */
    public function cancelar(int $id): int
    {
        return $this->execute(
            "UPDATE vales SET estado = 'cancelado', eliminado_en = NOW()
             WHERE id = :id AND stock_descontado = 0",
            [':id' => $id]
        );
    }
}
