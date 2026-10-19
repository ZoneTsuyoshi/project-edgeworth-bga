<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Data;

use Bga\Games\ProjectEdgeworth\Data\LinkRepository;
use ProjectEdgeworthTests\Support\DatabaseTestCase;

final class LinkRepositoryTest extends DatabaseTestCase
{
    private LinkRepository $links;

    protected function setUp(): void
    {
        parent::setUp();
        $this->links = new LinkRepository($this->db);
    }

    public function testSlotsFollowExistingCubeCountAndSimultaneousCubesStack(): void
    {
        // 003 §5.3: 先に 1 個ある区域へ 2 人が同時に置くと、同じスロット 1 に段 0・1
        $this->links->commit([['playerId' => 1, 'cell' => 20]], 1, 1);
        $placed = $this->links->commit([
            ['playerId' => 2, 'cell' => 20],
            ['playerId' => 3, 'cell' => 20],
            ['playerId' => 2, 'cell' => 21],
        ], 1, 2);
        $this->assertSame([[1, 0], [1, 1], [0, 0]], array_map(static fn($l): array => [$l->slot, $l->level], $placed));
        $this->assertSame([2, 2, 2], array_map(static fn($l): int => $l->commit, $placed));
        $this->assertSame([0, 1, 1], array_map(static fn($l): int => $l->slot, $this->links->inCell(20)));
    }

    public function testFirstCommitUsesSlotZeroForEveryone(): void
    {
        $placed = $this->links->commit([['playerId' => 1, 'cell' => 5], ['playerId' => 2, 'cell' => 5]], 3, 1);
        $this->assertSame([0, 0], array_map(static fn($l): int => $l->slot, $placed));
        $this->assertSame([0, 1], array_map(static fn($l): int => $l->level, $placed));
        $this->assertSame(3, $placed[0]->round);
    }

    public function testFifthCubeInACellThrows(): void
    {
        foreach ([1, 2, 3, 4] as $i => $player) {
            $this->links->commit([['playerId' => $player, 'cell' => 9]], 1, $i + 1);
        }
        $this->assertSame([0, 1, 2, 3], array_map(static fn($l): int => $l->slot, $this->links->inCell(9)));
        $this->expectException(\DomainException::class);
        $this->links->commit([['playerId' => 5, 'cell' => 9]], 1, 5);
    }

    public function testOwnerCanLinkACellOnlyOnce(): void
    {
        $this->links->commit([['playerId' => 1, 'cell' => 9]], 1, 1);
        $this->expectException(\PDOException::class);
        $this->links->commit([['playerId' => 1, 'cell' => 9]], 2, 2);
    }

    public function testCubeLimitIsSixteenPerPlayer(): void
    {
        for ($cell = 0; $cell < LinkRepository::CUBES_PER_PLAYER; $cell++) {
            $this->links->commit([['playerId' => 1, 'cell' => $cell]], 1, $cell + 1);
        }
        $this->assertSame(16, $this->links->countFor(1));
        $this->expectException(\DomainException::class);
        $this->links->commit([['playerId' => 1, 'cell' => 40]], 2, 99);
    }

    public function testQueries(): void
    {
        $this->links->commit([['playerId' => 1, 'cell' => 8], ['playerId' => 2, 'cell' => 8]], 1, 1);
        $this->links->commit([['playerId' => 1, 'cell' => 3]], 2, 2);
        $this->assertSame([3, 8], $this->links->cellsFor(1));
        $this->assertSame(2, $this->links->countFor(1));
        $this->assertSame(2, $this->links->countInCell(8));
        $this->assertTrue($this->links->hasLink(2, 8));
        $this->assertFalse($this->links->hasLink(2, 3));
        $this->assertCount(2, $this->links->forPlayer(1));
        $this->assertSame([1, 1, 2], array_map(static fn($l): int => $l->commit, $this->links->all()));
    }
}
