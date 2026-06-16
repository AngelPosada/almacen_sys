<?php
/**
 * core/BaseModel.php
 * 
 * Clase base para todos los Modelos del sistema.
 * Solo responsable de acceso a datos (consultas BD).
 * 
 * REGLA: Los modelos NO contienen lógica de negocio.
 *        Solo consultas limpias con PDO prepared statements.
 */

abstract class BaseModel
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ============================================================
    // HELPERS DE CONSULTA
    // ============================================================

    /**
     * Ejecuta una consulta y retorna todos los resultados.
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ejecuta una consulta y retorna un solo resultado.
     */
    protected function fetchOne(string $sql, array $params = []): array|false
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Ejecuta un INSERT y retorna el ID generado.
     */
    protected function insert(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Ejecuta un UPDATE o DELETE y retorna filas afectadas.
     */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Cuenta registros con una consulta dada.
     */
    protected function count(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Inicia una transacción.
     */
    protected function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * Confirma una transacción.
     */
    protected function commit(): void
    {
        $this->db->commit();
    }

    /**
     * Revierte una transacción.
     */
    protected function rollback(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /**
     * Paginación dinámica.
     * Retorna [items => [...], total => N, paginas => N, pagina_actual => N]
     */
    protected function paginate(
        string $sql,
        array  $params   = [],
        int    $pagina   = 1,
        int    $porPagina = 25
    ): array {
        // Total de registros
        $countSql = "SELECT COUNT(*) FROM ({$sql}) AS _count";
        $total    = $this->count($countSql, $params);

        // Paginación
        $offset     = ($pagina - 1) * $porPagina;
        $paginatedSql = $sql . " LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($paginatedSql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items'        => $stmt->fetchAll(),
            'total'        => $total,
            'paginas'      => (int) ceil($total / $porPagina),
            'pagina_actual' => $pagina,
            'por_pagina'   => $porPagina,
        ];
    }
}
