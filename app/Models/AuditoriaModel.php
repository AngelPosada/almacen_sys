<?php
/**
 * app/Models/AuditoriaModel.php
 *
 * Acceso a datos de la bitácora de auditoría.
 * Solo INSERT — la auditoría nunca se modifica ni elimina.
 */

class AuditoriaModel extends BaseModel
{
    /**
     * Registra una acción en la bitácora.
     * Llamado desde AuditoriaService — nunca directamente desde Controllers.
     */
    public function registrar(array $data): void
    {
        $this->insert(
            'INSERT INTO auditoria
               (usuario_id, modulo, accion, afectado_id,
                descripcion, datos_antes, datos_despues, ip, user_agent)
             VALUES
               (:usuario_id, :modulo, :accion, :afectado_id,
                :descripcion, :datos_antes, :datos_despues, :ip, :user_agent)',
            [
                ':usuario_id'    => $data['usuario_id']    ?? null,
                ':modulo'        => $data['modulo'],
                ':accion'        => $data['accion'],
                ':afectado_id'   => $data['afectado_id']   ?? null,
                ':descripcion'   => $data['descripcion']   ?? null,
                ':datos_antes'   => isset($data['datos_antes'])
                                      ? json_encode($data['datos_antes'], JSON_UNESCAPED_UNICODE)
                                      : null,
                ':datos_despues' => isset($data['datos_despues'])
                                      ? json_encode($data['datos_despues'], JSON_UNESCAPED_UNICODE)
                                      : null,
                ':ip'            => $data['ip']            ?? '0.0.0.0',
                ':user_agent'    => $data['user_agent']    ?? null,
            ]
        );
    }

    /**
     * Lista la bitácora con filtros opcionales (para el reporte de auditoría).
     */
    public function getFiltered(array $filtros = [], int $pagina = 1, int $porPagina = 50): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['usuario_id'])) {
            $where[]                = 'a.usuario_id = :usuario_id';
            $params[':usuario_id']  = $filtros['usuario_id'];
        }

        if (!empty($filtros['modulo'])) {
            $where[]           = 'a.modulo = :modulo';
            $params[':modulo'] = $filtros['modulo'];
        }

        if (!empty($filtros['accion'])) {
            $where[]           = 'a.accion = :accion';
            $params[':accion'] = $filtros['accion'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $where[]                 = 'a.creado_en >= :fecha_desde';
            $params[':fecha_desde']  = $filtros['fecha_desde'] . ' 00:00:00';
        }

        if (!empty($filtros['fecha_hasta'])) {
            $where[]                 = 'a.creado_en <= :fecha_hasta';
            $params[':fecha_hasta']  = $filtros['fecha_hasta'] . ' 23:59:59';
        }

        $sql = 'SELECT a.*, CONCAT(u.nombre, " ", u.apellidos) AS usuario_nombre, u.email
                FROM auditoria a
                LEFT JOIN usuarios u ON u.id = a.usuario_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.creado_en DESC';

        return $this->paginate($sql, $params, $pagina, $porPagina);
    }
}
