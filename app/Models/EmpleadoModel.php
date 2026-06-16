<?php
/**
 * app/Models/EmpleadoModel.php
 *
 * Consultas de base de datos para Empleados.
 * Modelo mínimo para dar soporte a Pedidos y Vales.
 * El CRUD completo de empleados se implementa en una fase posterior.
 */

class EmpleadoModel extends BaseModel
{
    /**
     * Lista empleados activos para selects.
     */
    public function getParaSelect(): array
    {
        return $this->fetchAll(
            'SELECT id, numero_empleado, nombre, apellidos, puesto, plantel
             FROM empleados
             WHERE activo = 1 AND eliminado_en IS NULL
             ORDER BY apellidos, nombre ASC'
        );
    }

    /**
     * Busca un empleado por ID.
     */
    public function findById(int $id): array|false
    {
        return $this->fetchOne(
            'SELECT * FROM empleados
             WHERE id = :id AND eliminado_en IS NULL LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Busca empleado por número de empleado (para búsqueda por QR o texto).
     */
    public function findByNumero(string $numero): array|false
    {
        return $this->fetchOne(
            'SELECT * FROM empleados
             WHERE numero_empleado = :num AND activo = 1 AND eliminado_en IS NULL LIMIT 1',
            [':num' => $numero]
        );
    }
}
