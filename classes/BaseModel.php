<?php
declare(strict_types=1);

/**
 * BaseModel Class
 * 
 * Abstract base class providing core Object-Oriented CRUD operations
 * and PDO database abstraction for AgriTrack entities.
 * Demonstrates OOP Inheritance, Encapsulation, and PDO Prepared Statements.
 */
abstract class BaseModel {
    protected PDO $pdo;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getLocalPdo();
    }

    /**
     * Get underlying PDO connection instance
     */
    public function getPdo(): PDO {
        return $this->pdo;
    }

    /**
     * Find single record by primary key
     */
    public function find(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Fetch all records with optional ordering
     */
    public function all(string $orderBy = ''): array {
        $sql = "SELECT * FROM {$this->table}";
        if ($orderBy !== '') {
            $sql .= " ORDER BY " . $orderBy;
        }
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Count records matching optional condition
     */
    public function count(string $where = '', array $params = []): int {
        $sql = "SELECT COUNT(*) as cnt FROM {$this->table}";
        if ($where !== '') {
            $sql .= " WHERE " . $where;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    /**
     * Delete record by primary key (mirrors to Supabase cloud if active)
     */
    public function delete(int $id): bool {
        return Database::delete($this->table, $this->primaryKey, $id);
    }

    /**
     * Execute parameterized query returning all rows
     */
    public function rawQuery(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute parameterized query returning single row
     */
    public function rawQueryOne(string $sql, array $params = []): ?array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Execute write statement (INSERT/UPDATE/DELETE)
     */
    public function rawExecute(string $sql, array $params = []): bool {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
}
