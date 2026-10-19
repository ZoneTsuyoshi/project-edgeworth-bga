<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Data;

use Bga\Games\ProjectEdgeworth\Data\PlayerValues;
use ProjectEdgeworthTests\Support\DatabaseTestCase;

final class PlayerValuesTest extends DatabaseTestCase
{
    private PlayerValues $players;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addPlayers([20, 10, 30]);
        $this->players = new PlayerValues($this->db);
    }

    public function testDefaultsAndSeatOrder(): void
    {
        $this->assertSame([20, 10, 30], $this->players->playerIds());
        $this->assertSame(0, $this->players->maya(10));
        $this->assertSame(PlayerValues::INITIAL_BUDGET, $this->players->budget(10));
        $this->assertNull($this->players->padCell(10));
        $this->assertNull($this->players->fameDetail(10));
    }

    public function testMayaCannotGoNegative(): void
    {
        $this->players->setMaya(10, 12);
        $this->assertSame(9, $this->players->addMaya(10, -3));
        $this->assertSame(14, $this->players->addMaya(10, 5));
        try {
            $this->players->addMaya(10, -15);
            $this->fail('maya must not go negative');
        } catch (\DomainException) {
        }
        $this->assertSame(14, $this->players->maya(10));
        $this->assertSame(0, $this->players->maya(20));
    }

    public function testBudget(): void
    {
        $this->assertSame(16, $this->players->addBudget(10, 1));
        $this->players->setBudget(10, 0);
        $this->expectException(\DomainException::class);
        $this->players->addBudget(10, -1);
    }

    public function testLaunchPadsCannotShareACell(): void
    {
        $this->players->setPadCell(10, 33);
        $this->assertSame(33, $this->players->padCell(10));
        $this->players->setPadCell(10, 33);
        try {
            $this->players->setPadCell(20, 33);
            $this->fail('cell already taken');
        } catch (\DomainException) {
        }
        $this->players->setPadCell(20, 34);
        $this->assertSame([20 => 34, 10 => 33], $this->players->padCells());
    }

    public function testFameDetailRoundTripsAndIsNotInPublicValues(): void
    {
        $detail = ['total' => 184, 'I' => ['points' => 28, 'budget' => 28]];
        $this->players->setFameDetail(10, $detail);
        $this->assertSame($detail, $this->players->fameDetail(10));
        $this->players->setMaya(10, 7);
        $public = $this->players->publicValues();
        $this->assertSame([20, 10, 30], array_keys($public));
        $this->assertSame(['maya' => 7, 'budget' => 15, 'padCell' => null], $public[10]);
        $this->players->setFameDetail(10, null);
        $this->assertNull($this->players->fameDetail(10));
    }
}
