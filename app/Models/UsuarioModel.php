<?php
/**
 * app/Models/UsuarioModel.php
 *
 * Consultas de base de datos relacionadas con usuarios.
 * Solo acceso a datos — sin lógica de negocio.
 */

class UsuarioModel extends BaseModel
{
    // ----------------------------------------------------------------
    // LECTURA
    // ----------------------------------------------------------------

    /**
     * Busca un usuario por su Google ID.
     */
    public function findByGoogleId(string $googleId): array|false
    {
        return $this->fetchOne(
            'SELECT * FROM usuarios WHERE google_id = :gid AND eliminado_en IS NULL LIMIT 1',
            [':gid' => $googleId]
        );
    }

    /**
     * Busca un usuario por su email.
     */
    public function findByEmail(string $email): array|false
    {
        return $this->fetchOne(
            'SELECT * FROM usuarios WHERE email = :email AND eliminado_en IS NULL LIMIT 1',
            [':email' => $email]
        );
    }

    /**
     * Obtiene un usuario por ID.
     */
    public function findById(int $id): array|false
    {
        return $this->fetchOne(
            'SELECT * FROM usuarios WHERE id = :id AND eliminado_en IS NULL LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Lista todos los usuarios activos (para panel de administración).
     */
    public function getAll(bool $incluirInactivos = false): array
    {
        $sql = 'SELECT id, nombre, apellidos, email, avatar_url, rol_id, activo,
                       ultimo_acceso, creado_en
                FROM usuarios
                WHERE eliminado_en IS NULL';

        if (!$incluirInactivos) {
            $sql .= ' AND activo = 1';
        }

        $sql .= ' ORDER BY nombre ASC';

        return $this->fetchAll($sql);
    }

    // ----------------------------------------------------------------
    // ESCRITURA
    // ----------------------------------------------------------------

    /**
     * Crea un nuevo usuario (primer login con Google).
     * Retorna el ID generado.
     */
    public function create(array $data): int
    {
        return $this->insert(
            'INSERT INTO usuarios
               (google_id, nombre, apellidos, email, avatar_url, rol_id, ultimo_acceso)
             VALUES
               (:google_id, :nombre, :apellidos, :email, :avatar_url, :rol_id, NOW())',
            [
                ':google_id'  => $data['google_id'],
                ':nombre'     => $data['nombre'],
                ':apellidos'  => $data['apellidos'] ?? '',
                ':email'      => $data['email'],
                ':avatar_url' => $data['avatar_url'] ?? null,
                ':rol_id'     => $data['rol_id']    ?? 3, // Default: Usuario
            ]
        );
    }

    /**
     * Actualiza datos del perfil al hacer login
     * (nombre, foto pueden cambiar en Google).
     */
    public function updateOnLogin(int $id, array $data): void
    {
        $this->execute(
            'UPDATE usuarios
             SET nombre = :nombre, apellidos = :apellidos,
                 avatar_url = :avatar_url, ultimo_acceso = NOW()
             WHERE id = :id',
            [
                ':nombre'     => $data['nombre'],
                ':apellidos'  => $data['apellidos'] ?? '',
                ':avatar_url' => $data['avatar_url'] ?? null,
                ':id'         => $id,
            ]
        );
    }

    /**
     * Actualiza el rol de un usuario (solo Admin).
     */
    public function updateRol(int $id, int $rolId): void
    {
        $this->execute(
            'UPDATE usuarios SET rol_id = :rol_id WHERE id = :id',
            [':rol_id' => $rolId, ':id' => $id]
        );
    }

    /**
     * Activa o desactiva un usuario.
     */
    public function toggleActivo(int $id, bool $activo): void
    {
        $this->execute(
            'UPDATE usuarios SET activo = :activo WHERE id = :id',
            [':activo' => (int) $activo, ':id' => $id]
        );
    }

    /**
     * Soft delete de un usuario.
     */
    public function softDelete(int $id): void
    {
        $this->execute(
            'UPDATE usuarios SET eliminado_en = NOW() WHERE id = :id',
            [':id' => $id]
        );
    }
}
