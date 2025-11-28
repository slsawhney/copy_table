<?php

namespace App\DB;

interface DatabaseConnectionInterface
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function prepare(string $sql): \PDOStatement;

    public function query(string $sql): \PDOStatement;

    public function lastInsertId(?string $name = null): string|false;

    public function getDriverName(): string;
}
