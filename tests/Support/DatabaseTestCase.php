<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support;

use PHPUnit\Framework\TestCase;

/**
 * 各テストの前にテーブルを作り直し、データ層を実 DB（MariaDB）につなぐ。
 */
abstract class DatabaseTestCase extends TestCase
{
    protected PdoDatabase $db;

    protected function setUp(): void
    {
        $this->db = PdoDatabase::connectFromEnv();
        Schema::reset($this->db);
    }

    /**
     * @param list<int> $ids 席順
     */
    protected function addPlayers(array $ids): void
    {
        foreach ($ids as $no => $id) {
            $this->db->execute("INSERT INTO `player` (`player_id`, `player_no`) VALUES ($id, " . ($no + 1) . ')');
        }
    }
}
