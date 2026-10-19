<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow;

use Bga\Games\ProjectEdgeworth\Board\Direction;
use Bga\Games\ProjectEdgeworth\Flow\RoundEnd;
use Bga\Games\ProjectEdgeworth\Material\SatelliteShape;

/** 009 の 110 EndCheck と 001 §10。 */
final class RoundEndTest extends FlowTestCase
{
    private function roundEnd(): RoundEnd
    {
        return new RoundEnd($this->satellites, $this->links, $this->playerValues, $this->globals);
    }

    /** @param int $count 稼働中にする衛星の数（形状の宣言順） */
    private function launch(int $playerId, int $count, int $startCell = 20): void
    {
        foreach (array_slice(SatelliteShape::cases(), 0, $count) as $i => $shape) {
            $this->satellites->launch($this->satellite($playerId, $shape)->id, $startCell + $i, Direction::NE);
        }
    }

    /** @param int $count 通信確立数 */
    private function link(int $playerId, int $count): void
    {
        $placements = [];
        for ($i = 0; $i < $count; $i++) {
            $placements[] = ['playerId' => $playerId, 'cell' => 40 + $i];
        }
        $this->links->commit($placements, 1, 1);
    }

    public function testContinuesWhenNobodyMeetsCondition(): void
    {
        $this->launch(101, 4);
        $this->link(102, 15);
        self::assertFalse($this->roundEnd()->check());
        self::assertSame([], $this->globals->endConditionPlayers());
    }

    public function testFiveActiveSatellitesEndsTheGame(): void
    {
        $this->launch(102, 5);
        self::assertTrue($this->roundEnd()->check());
        self::assertSame([102], $this->globals->endConditionPlayers());
    }

    public function testSixteenCubesEndsTheGame(): void
    {
        $this->link(101, 16);
        self::assertTrue($this->roundEnd()->check());
        self::assertSame([101], $this->globals->endConditionPlayers());
    }

    /** 001 §10: 同時成立は両方記録（名声 IX の +3 は各 1 回）。 */
    public function testBothConditionsAtTheSameTime(): void
    {
        $this->launch(101, 5);
        $this->link(102, 16);
        self::assertTrue($this->roundEnd()->check());
        self::assertSame([101, 102], $this->globals->endConditionPlayers());
    }

    /** FAQ 行 50: 循環フェイズで着陸して 4 機に戻れば満たさない。 */
    public function testLandingBeforeCheckPreventsEnd(): void
    {
        $this->launch(101, 4);
        $cell = $this->cellWithNextSea(true, Direction::NE, 3);
        $fifth = $this->satellite(101, SatelliteShape::Stealth);
        $this->satellites->launch($fifth->id, $cell, Direction::NE);
        $this->satellites->addFuelUsed($fifth->id, 2, 2);
        $this->orbit->run($this->geometry(), $this->scoringBoard());
        self::assertFalse($this->roundEnd()->check());
    }

    public function testGameProgressionIsMonotonic(): void
    {
        $this->launch(101, 4);
        $this->roundEnd()->check();
        self::assertSame(64, $this->globals->progressHighWater());
        $this->satellites->deactivate($this->satellite(101, SatelliteShape::Hammer)->id);
        $this->satellites->deactivate($this->satellite(101, SatelliteShape::Retro)->id);
        $this->roundEnd()->check();
        self::assertSame(64, $this->roundEnd()->gameProgression());
        $this->link(102, 12);
        self::assertSame(75, $this->roundEnd()->gameProgression());
    }
}
