<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Data;

use ProjectEdgeworthTests\Support\DatabaseTestCase;

/** dbmodel.sql が MariaDB に流せて、010 §3 のテーブルと列がそろうこと。 */
final class SchemaTest extends DatabaseTestCase
{
    public function testAllTablesExist(): void
    {
        $tables = array_map(
            static fn(array $r): string => (string) array_values($r)[0],
            $this->db->rows('SHOW TABLES'),
        );
        foreach (
            ['project_card', 'satellite', 'link', 'staged_link', 'region', 'action_card', 'mission_card', 'choice', 'pool',
                'held_notif', 'player'] as $table
        ) {
            $this->assertContains($table, $tables);
        }
    }

    public function testPlayerHasAddedColumns(): void
    {
        $columns = array_map(static fn(array $r): string => (string) $r['Field'], $this->db->rows('SHOW COLUMNS FROM `player`'));
        foreach (['maya', 'budget', 'pad_cell', 'fame_detail'] as $column) {
            $this->assertContains($column, $columns);
        }
    }

    public function testEveryTableHasPrimaryKeyAndNoColumnNamedAfterTable(): void
    {
        $tables = ['project_card', 'satellite', 'link', 'staged_link', 'region', 'action_card', 'mission_card', 'choice', 'pool',
            'held_notif'];
        foreach ($tables as $table) {
            $keys = $this->db->rows("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
            $this->assertNotSame([], $keys, "$table needs a primary key");
            foreach ($this->db->rows("SHOW COLUMNS FROM `$table`") as $column) {
                $this->assertNotSame($table, $column['Field'], 'D3: column name must differ from table name');
            }
        }
    }

    public function testDbmodelHasNoInlineComments(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 3) . '/src/dbmodel.sql');
        foreach (explode("\n", $sql) as $line) {
            $trimmed = ltrim($line);
            if (!str_starts_with($trimmed, '--')) {
                $this->assertStringNotContainsString('--', $line, 'D1: no comments on the same line as code');
            }
        }
    }
}
