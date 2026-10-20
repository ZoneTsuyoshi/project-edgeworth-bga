<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support;

use Bga\Games\projectedgeworth\Data\Database;

/**
 * データ層の Database インターフェースを PDO で実装したテスト用クラス。本番コードには入れない。
 */
final class PdoDatabase implements Database
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public static function connectFromEnv(): self
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'edgeworth_test';
        $pdo = new \PDO(
            "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASSWORD') ?: '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
        );
        return new self($pdo);
    }

    public function execute(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function rows(string $sql): array
    {
        $statement = $this->pdo->query($sql);
        if ($statement === false) {
            throw new \RuntimeException("query failed: $sql");
        }
        /** @var list<array<string, mixed>> */
        return $statement->fetchAll();
    }

    public function value(string $sql): mixed
    {
        $statement = $this->pdo->query($sql);
        if ($statement === false) {
            throw new \RuntimeException("query failed: $sql");
        }
        $value = $statement->fetchColumn();
        return $value === false ? null : $value;
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function escape(string $value): string
    {
        return substr($this->pdo->quote($value), 1, -1);
    }
}
