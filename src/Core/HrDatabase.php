<?php

namespace App\Core;

use PDO;
use PDOException;

class HrDatabase
{
    private static ?HrDatabase $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = require __DIR__ . '/../../config/database_hr.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if (!empty($config['ssl']['enabled'])) {
            if (!empty($config['ssl']['ca']))   $options[PDO::MYSQL_ATTR_SSL_CA]   = $config['ssl']['ca'];
            if (!empty($config['ssl']['cert'])) $options[PDO::MYSQL_ATTR_SSL_CERT] = $config['ssl']['cert'];
            if (!empty($config['ssl']['key']))  $options[PDO::MYSQL_ATTR_SSL_KEY]  = $config['ssl']['key'];
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (bool) ($config['ssl']['verify'] ?? false);
        }

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            throw new PDOException('Błąd połączenia z bazą danych HR: ' . $e->getMessage());
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function mainDbName(): string
    {
        static $name = null;
        if ($name === null) {
            $cfg = require __DIR__ . '/../../config/database.php';
            $name = $cfg['database'];
        }
        return $name;
    }

    public static function hrDbName(): string
    {
        static $name = null;
        if ($name === null) {
            $cfg = require __DIR__ . '/../../config/database_hr.php';
            $name = $cfg['database'];
        }
        return $name;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $table, array $data): int
    {
        $table = '`' . str_replace('`', '``', $table) . '`';
        $columns = implode(', ', array_map(fn($col) => '`' . str_replace('`', '``', $col) . '`', array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if (empty(trim($where))) {
            throw new \InvalidArgumentException('UPDATE without WHERE clause is not allowed');
        }
        $table = '`' . str_replace('`', '``', $table) . '`';
        $set = implode(', ', array_map(fn($col) => '`' . str_replace('`', '``', $col) . '` = ?', array_keys($data)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        $stmt = $this->query($sql, array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }
}
