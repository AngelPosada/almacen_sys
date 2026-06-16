<?php
/**
 * config/Database.php
 * 
 * Conexión PDO centralizada — patrón Singleton.
 * Un solo punto de entrada a la base de datos en todo el sistema.
 * 
 * Uso:
 *   $pdo = Database::getInstance();
 */

class Database
{
    private static ?PDO $instance = null;

    /**
     * Privado: no se permite instanciar externamente.
     */
    private function __construct() {}
    private function __clone() {}

    /**
     * Retorna la instancia única de PDO.
     * Crea la conexión en el primer llamado.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/config.php';
            $db     = $config['db'];

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['name'],
                $db['charset']
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            ];

            try {
                self::$instance = new PDO($dsn, $db['user'], $db['pass'], $options);
            } catch (PDOException $e) {
                // En producción: loggear sin exponer detalles
                Logger::error('DB_CONNECTION', $e->getMessage());
                http_response_code(500);
                die(json_encode([
                    'success' => false,
                    'message' => 'Error de conexión a la base de datos.'
                ]));
            }
        }

        return self::$instance;
    }

    /**
     * Permite cerrar la conexión explícitamente si se necesita.
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
