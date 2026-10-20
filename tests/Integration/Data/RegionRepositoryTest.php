<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Data;

use Bga\Games\projectedgeworth\Data\RegionRepository;
use ProjectEdgeworthTests\Support\DatabaseTestCase;

final class RegionRepositoryTest extends DatabaseTestCase
{
    public function testPlaceAndReadBack(): void
    {
        $regions = new RegionRepository($this->db);
        $regions->place(1, 0, 'C', true);
        $regions->place(0, 1, 'A', false);
        $this->assertSame([
            ['row' => 0, 'col' => 1, 'board' => 'A', 'flipped' => false],
            ['row' => 1, 'col' => 0, 'board' => 'C', 'flipped' => true],
        ], $regions->all());
    }

    public function testBoardAndPositionAreUnique(): void
    {
        $regions = new RegionRepository($this->db);
        $regions->place(0, 0, 'A', false);
        try {
            $regions->place(0, 1, 'A', false);
            $this->fail('same board twice');
        } catch (\PDOException) {
        }
        $this->expectException(\PDOException::class);
        $regions->place(0, 0, 'B', false);
    }

    public function testRejectsUnknownBoard(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RegionRepository($this->db))->place(0, 0, 'Z', false);
    }
}
