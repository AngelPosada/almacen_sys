<?php
/**
 * app/Models/RequisicionModel.php
 *
 * Consultas de base de datos para Requisiciones.
 *
 * REGLA CRÍTICA respetada aquí:
 *   La requisición NO mueve inventario.
 *   Este modelo no toca las tablas stock ni movimientos.
 *   Es un documento de solicitud de compra, no una salida de almacén.
 */

class RequisicionModel extends BaseModel
{
    // ----------------------------------------------------------------
    // FOLIO — generación atómica
    // ----------------------------------------------------------------

    private function generarFolio(): string
    {
        $anio  = date('Y');
        $ultima = $this->fetchOne(
            "SELECT folio FROM requisiciones
             WHERE folio LIKE :pat ORDER BY id DESC LIMIT 1 FOR UPDATE",
            [':pat' => "REQ-{$anio}-%"]
        );

        $sig = 1;
        if ($ultima) {
            $p   = explode('-', $ultima['folio']);
            $sig = ((int) end($p)) + 1;
        }
        return sprintf('REQ-%s-%05d', $anio, $sig);
    }

    // ----------------------------------------------------------------
    // LECTURA
    // ----------------------------------------------------------------

    public function getPaginadas(array $filtros = [], int $pagina = 1, int $porPagina = 25): array
    {
        $where  = ['r.eliminado_en IS NULL'];
        $params = [];

        if (!empty($filtros['estado'])) {
            $where[]           = 'r.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['plantel'])) {
            $where[]           = 'r.plantel LIKE :plantel';
            $params[':plantel']= '%' . $filtros['plantel'] . '%';
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[]              = 'r.fecha_elaboracion >= :desde';
            $params[':desde']     = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]              = 'r.fecha_elaboracion <= :hasta';
            $params[':hasta']     = $filtros['fecha_hasta'];
        }
        // Usuarios rol 3 ven solo las suyas
        if (!empty($filtros['solo_propias'])) {
            $where[]                   = 'r.solicita_usuario_id = :uid';
            $params[':uid']            = (int) $filtros['solo_propias'];
        }

        $sql = "SELECT
                  r.id, r.folio, r.plantel, r.estado,
                  r.total_estimado, r.fecha_elaboracion,
                  r.cotizaciones_requeridas, r.creado_en,
                  CONCAT(us.nombre,' ',us.apellidos) AS solicita_nombre
                FROM requisiciones r
                JOIN usuarios us ON us.id = r.solicita_usuario_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY r.creado_en DESC";

        return $this->paginate($sql, $params, $pagina, $porPagina);
    }

    public function findById(int $id): array|false
    {
        $req = $this->fetchOne(
            "SELECT r.*,
                    CONCAT(us.nombre,' ',us.apellidos)  AS solicita_nombre,
                    CONCAT(uv.nombre,' ',uv.apellidos)  AS valida_nombre,
                    CONCAT(ua.nombre,' ',ua.apellidos)  AS autoriza_nombre,
                    CONCAT(uc.nombre,' ',uc.apellidos)  AS comprador_nombre
             FROM requisiciones r
             JOIN  usuarios us ON us.id = r.solicita_usuario_id
             LEFT JOIN usuarios uv ON uv.id = r.valida_usuario_id
             LEFT JOIN usuarios ua ON ua.id = r.autoriza_usuario_id
             LEFT JOIN usuarios uc ON uc.id = r.asignado_comprador_id
             WHERE r.id = :id AND r.eliminado_en IS NULL LIMIT 1",
            [':id' => $id]
        );

        if (!$req) return false;

        $req['items'] = $this->fetchAll(
            'SELECT * FROM requisicion_items
             WHERE requisicion_id = :rid ORDER BY numero_item ASC',
            [':rid' => $id]
        );

        return $req;
    }

    public function contarPorEstado(): array
    {
        $rows = $this->fetchAll(
            'SELECT estado, COUNT(*) AS total
             FROM requisiciones WHERE eliminado_en IS NULL GROUP BY estado'
        );
        $result = [];
        foreach ($rows as $r) $result[$r['estado']] = (int) $r['total'];
        return $result;
    }

    // ----------------------------------------------------------------
    // ESCRITURA
    // ----------------------------------------------------------------

    /**
     * Crea la requisición con sus ítems en una transacción.
     * Calcula automáticamente total_estimado y cotizaciones_requeridas.
     */
    public function create(array $cabecera, array $items, float $umbralCotizacion = 25000): int
    {
        $this->beginTransaction();
        try {
            $folio = $this->generarFolio();

            // Calcular total y flag de cotizaciones
            $totalEstimado = array_sum(array_column($items, 'total'));
            $cotizReq      = $totalEstimado > $umbralCotizacion ? 1 : 0;

            $reqId = $this->insert(
                'INSERT INTO requisiciones
                   (folio, plantel, clave_programatica, fecha_elaboracion,
                    justificacion, total_estimado, cotizaciones_requeridas,
                    solicita_usuario_id, observaciones)
                 VALUES
                   (:folio,:plantel,:clave,:fecha,
                    :just,:total,:cotiz,
                    :sol_id,:obs)',
                [
                    ':folio'   => $folio,
                    ':plantel' => $cabecera['plantel'],
                    ':clave'   => $cabecera['clave_programatica']  ?? null,
                    ':fecha'   => $cabecera['fecha_elaboracion'],
                    ':just'    => $cabecera['justificacion']       ?? null,
                    ':total'   => $totalEstimado,
                    ':cotiz'   => $cotizReq,
                    ':sol_id'  => $cabecera['solicita_usuario_id'],
                    ':obs'     => $cabecera['observaciones']       ?? null,
                ]
            );

            foreach ($items as $item) {
                $this->insert(
                    'INSERT INTO requisicion_items
                       (requisicion_id, numero_item, concepto,
                        cantidad, especificaciones, precio_unitario, total)
                     VALUES (:rid,:num,:con,:cant,:esp,:precio,:total)',
                    [
                        ':rid'    => $reqId,
                        ':num'    => (int)   $item['numero_item'],
                        ':con'    => $item['concepto'],
                        ':cant'   => (float) $item['cantidad'],
                        ':esp'    => $item['especificaciones'] ?? null,
                        ':precio' => (float) $item['precio_unitario'],
                        ':total'  => (float) $item['total'],
                    ]
                );
            }

            $this->commit();
            return $reqId;

        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Transición de estado con registro del usuario que la ejecuta.
     */
    public function cambiarEstado(int $id, string $estado, int $usuarioId): void
    {
        $campoUsuario = match ($estado) {
            'validada'   => 'valida_usuario_id',
            'autorizada' => 'autoriza_usuario_id',
            default      => null,
        };

        $sql    = 'UPDATE requisiciones SET estado = :estado';
        $params = [':estado' => $estado, ':id' => $id];

        if ($campoUsuario) {
            $sql .= ", {$campoUsuario} = :uid";
            $params[':uid'] = $usuarioId;
        }
        if ($estado === 'comprada' || $estado === 'cancelada') {
            $sql .= ', fecha_cierre = CURDATE()';
        }

        $sql .= ' WHERE id = :id';
        $this->execute($sql, $params);
    }

    /**
     * Asigna comprador y fecha de asignación.
     */
    public function asignarComprador(int $id, int $compradorId): void
    {
        $this->execute(
            'UPDATE requisiciones
             SET asignado_comprador_id = :uid, fecha_asignacion = CURDATE()
             WHERE id = :id',
            [':uid' => $compradorId, ':id' => $id]
        );
    }

    /**
     * Actualiza datos del área interna (padrón, cierre).
     */
    public function actualizarInterno(int $id, array $data): void
    {
        $this->execute(
            'UPDATE requisiciones
             SET en_padron_proveedores = :padron,
                 fecha_cierre          = :cierre
             WHERE id = :id',
            [
                ':padron' => isset($data['en_padron_proveedores'])
                             ? (int) $data['en_padron_proveedores'] : null,
                ':cierre' => $data['fecha_cierre'] ?? null,
                ':id'     => $id,
            ]
        );
    }

    public function softDelete(int $id): void
    {
        $this->execute(
            'UPDATE requisiciones SET eliminado_en = NOW() WHERE id = :id',
            [':id' => $id]
        );
    }
}
