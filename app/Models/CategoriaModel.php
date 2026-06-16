<?php
/**
 * app/Models/CategoriaModel.php
 *
 * Consultas de base de datos para Categorías.
 * Árbol de dos niveles: raíz → hijos.
 */

class CategoriaModel extends BaseModel
{
    // ----------------------------------------------------------------
    // LECTURA
    // ----------------------------------------------------------------

    /**
     * Lista todas las categorías activas con conteo de productos.
     * Incluye el nombre de la categoría padre si existe.
     */
    public function getAll(bool $soloRaiz = false): array
    {
        $where = $soloRaiz ? 'AND c.parent_id IS NULL' : '';

        return $this->fetchAll(
            "SELECT
               c.id,
               c.nombre,
               c.descripcion,
               c.parent_id,
               c.icono,
               c.color,
               c.activo,
               c.creado_en,
               p.nombre            AS parent_nombre,
               COUNT(pr.id)        AS total_productos
             FROM categorias c
             LEFT JOIN categorias p  ON p.id  = c.parent_id
             LEFT JOIN productos  pr ON pr.categoria_id = c.id
                                    AND pr.activo = 1
                                    AND pr.eliminado_en IS NULL
             WHERE c.eliminado_en IS NULL
             {$where}
             GROUP BY c.id
             ORDER BY c.parent_id IS NOT NULL, c.parent_id, c.nombre ASC"
        );
    }

    /**
     * Categorías raíz activas — para selects/dropdowns.
     */
    public function getRaices(): array
    {
        return $this->fetchAll(
            'SELECT id, nombre, icono, color
             FROM categorias
             WHERE parent_id IS NULL
               AND activo = 1
               AND eliminado_en IS NULL
             ORDER BY nombre ASC'
        );
    }

    /**
     * Todas las categorías activas — para el select de producto.
     * Formato: padre › hijo para legibilidad.
     */
    public function getParaSelect(): array
    {
        $rows = $this->fetchAll(
            'SELECT c.id, c.nombre, c.parent_id, p.nombre AS parent_nombre
             FROM categorias c
             LEFT JOIN categorias p ON p.id = c.parent_id
             WHERE c.activo = 1 AND c.eliminado_en IS NULL
             ORDER BY COALESCE(c.parent_id, c.id), c.nombre ASC'
        );

        return array_map(function (array $r): array {
            return [
                'id'     => $r['id'],
                'nombre' => $r['parent_nombre']
                    ? $r['parent_nombre'] . ' › ' . $r['nombre']
                    : $r['nombre'],
            ];
        }, $rows);
    }

    /**
     * Busca una categoría por ID.
     */
    public function findById(int $id): array|false
    {
        return $this->fetchOne(
            'SELECT * FROM categorias WHERE id = :id AND eliminado_en IS NULL LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Verifica si ya existe una categoría con ese nombre (dentro del mismo nivel).
     * Usado para validación de duplicados.
     */
    public function existeNombre(string $nombre, ?int $parentId, ?int $excludeId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM categorias
                   WHERE nombre = :nombre
                     AND eliminado_en IS NULL';
        $params = [':nombre' => $nombre];

        if ($parentId === null) {
            $sql .= ' AND parent_id IS NULL';
        } else {
            $sql .= ' AND parent_id = :parent_id';
            $params[':parent_id'] = $parentId;
        }

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        return $this->count($sql, $params) > 0;
    }

    /**
     * Verifica si una categoría tiene productos asignados.
     * Usado antes de eliminar.
     */
    public function tieneProductos(int $id): bool
    {
        return $this->count(
            'SELECT COUNT(*) FROM productos
             WHERE categoria_id = :id AND eliminado_en IS NULL',
            [':id' => $id]
        ) > 0;
    }

    /**
     * Verifica si una categoría tiene subcategorías.
     */
    public function tieneHijos(int $id): bool
    {
        return $this->count(
            'SELECT COUNT(*) FROM categorias
             WHERE parent_id = :id AND eliminado_en IS NULL',
            [':id' => $id]
        ) > 0;
    }

    // ----------------------------------------------------------------
    // ESCRITURA
    // ----------------------------------------------------------------

    public function create(array $data): int
    {
        return $this->insert(
            'INSERT INTO categorias (nombre, descripcion, parent_id, icono, color)
             VALUES (:nombre, :descripcion, :parent_id, :icono, :color)',
            [
                ':nombre'      => $data['nombre'],
                ':descripcion' => $data['descripcion'] ?? null,
                ':parent_id'   => $data['parent_id']   ?? null,
                ':icono'       => $data['icono']        ?? 'ti-tag',
                ':color'       => $data['color']        ?? '#0E734E',
            ]
        );
    }

    public function update(int $id, array $data): void
    {
        $this->execute(
            'UPDATE categorias
             SET nombre = :nombre, descripcion = :descripcion,
                 parent_id = :parent_id, icono = :icono, color = :color,
                 activo = :activo
             WHERE id = :id',
            [
                ':nombre'      => $data['nombre'],
                ':descripcion' => $data['descripcion'] ?? null,
                ':parent_id'   => $data['parent_id']   ?? null,
                ':icono'       => $data['icono']        ?? 'ti-tag',
                ':color'       => $data['color']        ?? '#0E734E',
                ':activo'      => (int) ($data['activo'] ?? 1),
                ':id'          => $id,
            ]
        );
    }

    public function softDelete(int $id): void
    {
        $this->execute(
            'UPDATE categorias SET eliminado_en = NOW() WHERE id = :id',
            [':id' => $id]
        );
    }
}
