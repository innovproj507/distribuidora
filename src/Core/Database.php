<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Database - Singleton PDO para empresa única (sin multi-tenancy)
 */
class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = [
                'host'     => $_ENV['DB_HOST']     ?? 'localhost',
                'port'     => $_ENV['DB_PORT']     ?? '3306',
                'database' => $_ENV['DB_DATABASE'] ?? 'pos_empresa',
                'username' => $_ENV['DB_USERNAME'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
            ];

            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";

            try {
                self::$instance = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES'",
                ]);
            } catch (PDOException $e) {
                if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                    die('Error de conexión: ' . $e->getMessage());
                }
                die('Error de conexión a la base de datos.');
            }
        }

        return self::$instance;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }

    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    public static function rollback(): bool
    {
        return self::getInstance()->rollBack();
    }
}
