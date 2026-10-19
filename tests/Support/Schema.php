<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support;

/**
 * テスト用のテーブルを作り直す。src/dbmodel.sql をそのまま流し、BGA が先に作る player テーブルは最小限で代用する。
 */
final class Schema
{
    private const TABLES = [
        'project_card', 'satellite', 'link', 'staged_link', 'region', 'action_card',
        'mission_card', 'choice', 'pool', 'held_notif', 'player',
    ];

    public static function reset(PdoDatabase $db): void
    {
        foreach (self::TABLES as $table) {
            $db->execute("DROP TABLE IF EXISTS `$table`");
        }
        // BGA が作る player テーブルの、データ層が使う列だけ
        $db->execute(
            'CREATE TABLE `player` ('
            . '`player_id` INT UNSIGNED NOT NULL, `player_no` INT NOT NULL DEFAULT 0, '
            . '`player_name` VARCHAR(100) NOT NULL DEFAULT \'\', `player_score` INT NOT NULL DEFAULT 0, '
            . 'PRIMARY KEY (`player_id`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        );
        foreach (self::statements(dirname(__DIR__, 2) . '/src/dbmodel.sql') as $sql) {
            $db->execute($sql);
        }
    }

    /**
     * dbmodel.sql の `--` コメント行を除き、`;` で文に分ける。
     *
     * @return list<string>
     */
    public static function statements(string $path): array
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException("cannot read $path");
        }
        $lines = array_filter(
            explode("\n", $sql),
            static fn(string $line): bool => !str_starts_with(ltrim($line), '--'),
        );
        $statements = array_map('trim', explode(';', implode("\n", $lines)));
        return array_values(array_filter($statements, static fn(string $s): bool => $s !== ''));
    }
}
