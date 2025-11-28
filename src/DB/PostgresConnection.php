<?php

namespace App\DB;

use PDO;
use PDOException;

class PostgresConnection implements DatabaseConnectionInterface
{
    private PDO $pdo;

    public function __construct(
        string $host,
        int $port,
        string $dbname,
        string $user,
        string $password,
        ?int $timeoutSeconds = 5
    ) {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => $timeoutSeconds ?? 5,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $password, $options);
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
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
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function prepare(string $sql): \PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    public function query(string $sql): \PDOStatement
    {
        return $this->pdo->query($sql);
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    public function getDriverName(): string
    {
        return 'pgsql';
    }
}
