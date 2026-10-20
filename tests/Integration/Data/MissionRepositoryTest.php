<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Data;

use Bga\Games\projectedgeworth\Data\MissionRepository;
use ProjectEdgeworthTests\Support\DatabaseTestCase;

final class MissionRepositoryTest extends DatabaseTestCase
{
    private MissionRepository $missions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->missions = new MissionRepository($this->db);
        $this->missions->createAll();
    }

    public function testDealGivesTwoToEachPlayerAndLeavesTheRest(): void
    {
        $this->missions->deal(10, [3, 9]);
        $this->missions->deal(11, [1, 5]);
        $this->assertSame([3, 9], $this->missions->forPlayer(10));
        $this->assertSame([1, 5], $this->missions->forPlayer(11));
        $this->assertSame([2, 4, 6, 7, 8, 10], $this->missions->undealt());
        $this->assertSame([10 => [3, 9], 11 => [1, 5]], $this->missions->allDealt());
    }

    public function testCannotDealSameMissionTwice(): void
    {
        $this->missions->deal(10, [3, 9]);
        $this->expectException(\DomainException::class);
        $this->missions->deal(11, [9, 2]);
    }

    public function testCannotDealToSamePlayerTwiceOrWrongCount(): void
    {
        $this->missions->deal(10, [3, 9]);
        try {
            $this->missions->deal(10, [1, 2]);
            $this->fail('already dealt');
        } catch (\DomainException) {
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->missions->deal(11, [1]);
    }

    public function testFailedDealChangesNothing(): void
    {
        $this->missions->deal(10, [3, 9]);
        try {
            $this->missions->deal(11, [1, 9]);
        } catch (\DomainException) {
        }
        $this->assertSame([], $this->missions->forPlayer(11));
        $this->assertCount(8, $this->missions->undealt());
    }
}
