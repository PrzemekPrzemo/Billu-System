<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = require __DIR__ . '/../../config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => true,
            ]);
        } catch (PDOException $e) {
            throw new PDOException('Błąd połączenia z bazą danych: ' . $e->getMessage());
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
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

    /**
     * Multi-row INSERT z jednym round-tripem do bazy. Zwraca liczbę zapisanych wierszy.
     * Wszystkie wiersze muszą mieć ten sam zestaw kolumn (kolejność z pierwszego elementu).
     *
     * @param string $table
     * @param array<int,array<string,mixed>> $rows
     * @return int
     */
    public function bulkInsert(string $table, array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }
        $columns = array_keys($rows[0]);
        if (empty($columns)) {
            return 0;
        }

        $tableEsc = '`' . str_replace('`', '``', $table) . '`';
        $colsEsc  = implode(', ', array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', $columns));
        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $placeholders = implode(', ', array_fill(0, count($rows), $rowPlaceholder));

        $params = [];
        foreach ($rows as $row) {
            foreach ($columns as $col) {
                $params[] = $row[$col] ?? null;
            }
        }

        $sql = "INSERT INTO {$tableEsc} ({$colsEsc}) VALUES {$placeholders}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Diagnostyka: zwraca wynik EXPLAIN dla podanego zapytania.
     * Używać tylko w trybie debug / lokalnie - może ujawniać strukturę bazy.
     *
     * @return array<int,array<string,mixed>>
     */
    public function explain(string $sql, array $params = []): array
    {
        return $this->fetchAll('EXPLAIN ' . $sql, $params);
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
