<?php

class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dir = dirname(DB_PATH);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            self::$pdo = new PDO('sqlite:' . DB_PATH, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA journal_mode = WAL');
        }
        return self::$pdo;
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = [])
    {
        $v = self::q($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $ph = implode(', ', array_fill(0, count($data), '?'));
        self::q("INSERT INTO $table ($cols) VALUES ($ph)", array_values($data));
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $w = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));
        return self::q("UPDATE $table SET $set WHERE $w", array_merge(array_values($data), array_values($where)))->rowCount();
    }

    public static function delete(string $table, array $where): int
    {
        $w = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));
        return self::q("DELETE FROM $table WHERE $w", array_values($where))->rowCount();
    }

    public static function transaction(callable $fn): void
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $fn();
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}