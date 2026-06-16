<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * app/Models/ConfiguracionModel.php
 *
 * Acceso a la tabla configuracion.
 * Clave-valor con tipos: texto, numero, booleano, json, imagen.
 */

class ConfiguracionModel extends BaseModel
{
    /**
     * Retorna toda la configuración como array asociativo clave => valor.
     */
    public function getAll(): array
    {
        $filas = $this->fetchAll(
            'SELECT clave, valor, descripcion, tipo
             FROM configuracion
             ORDER BY clave ASC'
        );

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[$fila['clave']] = [
                'valor'       => $fila['valor'],
                'descripcion' => $fila['descripcion'],
                'tipo'        => $fila['tipo'],
            ];
        }
        return $resultado;
    }

    /**
     * Obtiene el valor de una clave específica.
     */
    public function get(string $clave, mixed $default = null): mixed
    {
        $fila = $this->fetchOne(
            'SELECT valor, tipo FROM configuracion WHERE clave = :clave LIMIT 1',
            [':clave' => $clave]
        );

        if (!$fila) return $default;

        return $this->castValor($fila['valor'], $fila['tipo']);
    }

    /**
     * Actualiza o inserta una clave de configuración.
     */
    public function set(string $clave, mixed $valor, int $usuarioId): void
    {
        $this->execute(
            'UPDATE configuracion
             SET valor = :valor, actualizado_por = :uid
             WHERE clave = :clave',
            [
                ':valor' => (string) $valor,
                ':uid'   => $usuarioId,
                ':clave' => $clave,
            ]
        );
    }

    /**
     * Actualiza múltiples claves en una sola operación.
     */
    public function setMultiple(array $datos, int $usuarioId): void
    {
        $this->beginTransaction();
        try {
            foreach ($datos as $clave => $valor) {
                $this->set($clave, $valor, $usuarioId);
            }
            $this->commit();
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------------

    private function castValor(mixed $valor, string $tipo): mixed
    {
        return match ($tipo) {
            'numero'   => (float) $valor,
            'booleano' => (bool) (int) $valor,
            'json'     => json_decode($valor, true),
            default    => $valor,
        };
    }
}
