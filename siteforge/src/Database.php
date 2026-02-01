<?php
/**
 * Database Connection Class
 * 
 * Singleton PDO wrapper para a base de dados local do cliente.
 */

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Inicializa a configuração da base de dados.
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Obtém a instância PDO (singleton).
     */
    public static function getInstance(): ?PDO
    {
        if (!self::$config['enabled']) {
            return null;
        }

        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    self::$config['host'],
                    self::$config['port'],
                    self::$config['name'],
                    self::$config['charset']
                );

                self::$instance = new PDO($dsn, self::$config['user'], self::$config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                return null;
            }
        }

        return self::$instance;
    }

    /**
     * Executa uma query com parâmetros.
     */
    public static function query(string $sql, array $params = []): ?\PDOStatement
    {
        $pdo = self::getInstance();
        if (!$pdo) return null;

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            return null;
        }
    }

    /**
     * Fetch single row.
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        return $stmt ? ($stmt->fetch() ?: null) : null;
    }

    /**
     * Fetch all rows.
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Insert and return last insert ID.
     */
    public static function insert(string $table, array $data): ?int
    {
        $pdo = self::getInstance();
        if (!$pdo) return null;

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('Insert failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update rows.
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $pdo = self::getInstance();
        if (!$pdo) return 0;

        $set = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([...array_values($data), ...$whereParams]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log('Update failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Delete rows.
     */
    public static function delete(string $table, string $where, array $params = []): int
    {
        $pdo = self::getInstance();
        if (!$pdo) return 0;

        $sql = "DELETE FROM {$table} WHERE {$where}";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log('Delete failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Begin transaction.
     */
    public static function beginTransaction(): bool
    {
        $pdo = self::getInstance();
        return $pdo ? $pdo->beginTransaction() : false;
    }

    /**
     * Commit transaction.
     */
    public static function commit(): bool
    {
        $pdo = self::getInstance();
        return $pdo ? $pdo->commit() : false;
    }

    /**
     * Rollback transaction.
     */
    public static function rollback(): bool
    {
        $pdo = self::getInstance();
        return $pdo ? $pdo->rollBack() : false;
    }
}
