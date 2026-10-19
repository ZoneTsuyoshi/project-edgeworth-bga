<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Data;

/**
 * SQL の値リテラルを組み立てる小さな補助（リポジトリ内部用）。
 */
final class Sql
{
    private function __construct()
    {
    }

    public static function str(Database $db, string $value): string
    {
        return "'" . $db->escape($value) . "'";
    }

    public static function nullableStr(Database $db, ?string $value): string
    {
        return $value === null ? 'NULL' : self::str($db, $value);
    }

    public static function nullableInt(?int $value): string
    {
        return $value === null ? 'NULL' : (string) $value;
    }

    /**
     * @param mixed $value JSON にして保存する値
     */
    public static function json(Database $db, mixed $value): string
    {
        return self::str($db, json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public static function nullableJson(Database $db, mixed $value): string
    {
        return $value === null ? 'NULL' : self::json($db, $value);
    }

    /**
     * @return array<mixed>|null
     */
    public static function decode(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param list<int> $ids
     */
    public static function intList(array $ids): string
    {
        return implode(',', array_map('intval', $ids));
    }
}
