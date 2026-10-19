<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Data;

use Bga\Games\ProjectEdgeworth\Data\LinkRepository;
use Bga\Games\ProjectEdgeworth\Data\PlayerValues;
use Bga\Games\ProjectEdgeworth\Data\StagedLinkRepository;
use ProjectEdgeworthTests\Support\DatabaseTestCase;

final class StagedLinkRepositoryTest extends DatabaseTestCase
{
    private StagedLinkRepository $staged;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addPlayers([1, 2]);
        (new PlayerValues($this->db))->setMaya(1, 20);
        $this->staged = new StagedLinkRepository($this->db);
    }

    public function testStageComputesReservationAndKeepsPioneerBreakdown(): void
    {
        $id = $this->staged->stage(1, 12, 100, true, 'ground_link', 3, 5, [['to' => 2, 'amount' => 3]]);
        $row = $this->staged->forPlayer(1)[0];
        $this->assertSame($id, $row->id);
        $this->assertSame(11, $row->reserved);
        $this->assertTrue($row->special);
        $this->assertSame('ground_link', $row->source);
        $this->assertSame([['to' => 2, 'amount' => 3]], $row->pioneer);
        $this->assertSame(11, $this->staged->reservedMaya(1));
        $this->assertSame(9, $this->staged->availableMaya(1));
        $this->assertSame(0, $this->staged->reservedMaya(2));
    }

    public function testRemainingCubesCountLinksAndStages(): void
    {
        (new LinkRepository($this->db))->commit([['playerId' => 1, 'cell' => 1]], 1, 1);
        $this->staged->stage(1, 2, 100, false, 'x', 1, 0, []);
        $this->assertSame(14, $this->staged->remainingCubes(1));
        $this->assertSame(16, $this->staged->remainingCubes(2));
    }

    public function testCannotStageBeyondCubeLimit(): void
    {
        for ($cell = 0; $cell < 16; $cell++) {
            $this->staged->stage(1, $cell, 100, false, 'x', 0, 0, []);
        }
        $this->expectException(\DomainException::class);
        $this->staged->stage(1, 99, 100, false, 'x', 0, 0, []);
    }

    public function testCannotStageCellAlreadyLinkedOrStaged(): void
    {
        (new LinkRepository($this->db))->commit([['playerId' => 1, 'cell' => 4]], 1, 1);
        try {
            $this->staged->stage(1, 4, 100, false, 'x', 0, 0, []);
            $this->fail('already linked');
        } catch (\DomainException) {
        }
        $this->staged->stage(1, 5, 100, false, 'x', 0, 0, []);
        $this->expectException(\DomainException::class);
        $this->staged->stage(1, 5, 100, false, 'x', 0, 0, []);
    }

    public function testRemoveAndClear(): void
    {
        $a = $this->staged->stage(1, 1, 100, false, 'x', 0, 0, []);
        $this->staged->stage(2, 2, 101, false, 'x', 0, 0, []);
        $this->staged->remove($a);
        $this->assertSame(1, $this->staged->count());
        $this->staged->clear();
        $this->assertSame(0, $this->staged->count());
        $this->assertSame([], $this->staged->all());
    }
}
