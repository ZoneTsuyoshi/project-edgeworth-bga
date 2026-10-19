<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow;

use Bga\Games\ProjectEdgeworth\Board\Direction;
use Bga\Games\ProjectEdgeworth\Data\CardLocation;
use Bga\Games\ProjectEdgeworth\Data\MissionRepository;
use Bga\Games\ProjectEdgeworth\Data\RegionRepository;
use Bga\Games\ProjectEdgeworth\Flow\FinalScoring;
use Bga\Games\ProjectEdgeworth\Material\SatelliteCards;
use Bga\Games\ProjectEdgeworth\Material\SatelliteShape;

/** 009 の 121 FinalScoring。DB の状態が FameCalculator に渡り、内訳が保存される。 */
final class FinalScoringTest extends FlowTestCase
{
    private function scoring(): FinalScoring
    {
        return new FinalScoring(
            $this->satellites,
            $this->cards,
            $this->links,
            new MissionRepository($this->db),
            new RegionRepository($this->db),
            $this->playerValues,
            $this->globals,
        );
    }

    public function testSavesFameDetailForEveryPlayer(): void
    {
        $this->globals->setEndConditionPlayers([101]);
        $results = $this->scoring()->run();

        self::assertSame([101, 102], array_keys($results));
        foreach ($results as $playerId => $result) {
            $detail = $this->playerValues->fameDetail($playerId);
            self::assertNotNull($detail);
            self::assertSame($result->total, $detail['total']);
            self::assertSame($result->detail, $detail);
            $sum = 0;
            foreach (['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'] as $section) {
                $sum += $detail[$section]['points'];
            }
            self::assertSame($sum, $detail['total']);
        }
        self::assertSame(3, $results[101]->points('IX'));
        self::assertSame(0, $results[102]->points('IX'));
    }

    /** Q-12: 終了時の手札は 1 枚 3 マヤに換金してから II を計算する。 */
    public function testHandIsCashedOutBeforeScoring(): void
    {
        $hand = count($this->cards->idsIn(CardLocation::Hand, 101));
        self::assertGreaterThan(0, $hand);
        $maya = $this->playerValues->maya(101);
        $result = $this->scoring()->run()[101];
        self::assertSame(intdiv($maya + 3 * $hand, 10), $result->points('II'));
        self::assertSame($this->playerValues->budget(101), $result->points('I'));
    }

    /** 稼働中の衛星は VI、通信確立と発射拠点は III に入る。 */
    public function testActiveSatellitesAndLinks(): void
    {
        $sat = $this->satellite(101, SatelliteShape::Concorde);
        $this->satellites->launch($sat->id, 10, Direction::NE);
        $this->links->commit([['playerId' => 101, 'cell' => 30]], 1, 1);
        $this->playerValues->setPadCell(101, 7);
        $result = $this->scoring()->run()[101];

        self::assertSame(SatelliteCards::get($sat->cardId)->fame, $result->points('VI'));
        $cells = $result->detail['III']['cells'];
        self::assertContains(30, $cells);
        self::assertContains(7, $cells);
    }

    public function testScoringBoardMatchesSetupLayout(): void
    {
        $board = $this->scoring()->scoringBoard();
        for ($id = 0; $id < $this->geometry()->cellCount(); $id++) {
            self::assertSame($this->layout->areaType($id), $board->areaType($id));
            self::assertSame($this->layout->region($id), $board->region($id));
        }
    }
}
