<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow;

use Bga\Games\projectedgeworth\Board\BoardGeometry;
use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Board\Movement;
use Bga\Games\projectedgeworth\Board\SatellitePosition;
use Bga\Games\projectedgeworth\Data\LinkRepository;
use Bga\Games\projectedgeworth\Data\PlayerValues;
use Bga\Games\projectedgeworth\Data\ProjectCardRepository;
use Bga\Games\projectedgeworth\Data\Satellite;
use Bga\Games\projectedgeworth\Data\SatelliteRepository;
use Bga\Games\projectedgeworth\Flow\OrbitPhase;
use Bga\Games\projectedgeworth\Material\SatelliteShape;
use Bga\Games\projectedgeworth\Scoring\ScoringBoard;
use ProjectEdgeworthTests\Integration\Setup\SetupTestCase;
use ProjectEdgeworthTests\Support\SeededRandom;

/** 循環フェイズ・終了判定・最終得点の結合テストの共通部品。2 人（101・102）でセットアップ済みにする。 */
abstract class FlowTestCase extends SetupTestCase
{
    protected BoardLayout $layout;
    protected SatelliteRepository $satellites;
    protected ProjectCardRepository $cards;
    protected PlayerValues $playerValues;
    protected LinkRepository $links;
    protected OrbitPhase $orbit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->layout = $this->runSetup(2);
        $this->satellites = new SatelliteRepository($this->db);
        $this->cards = new ProjectCardRepository($this->db, new SeededRandom(1));
        $this->playerValues = new PlayerValues($this->db);
        $this->links = new LinkRepository($this->db);
        $this->orbit = new OrbitPhase($this->satellites, $this->cards, $this->playerValues);
    }

    protected function geometry(): BoardGeometry
    {
        return $this->layout->geometry();
    }

    protected function scoringBoard(): ScoringBoard
    {
        return ScoringBoard::fromLayout($this->layout);
    }

    protected function satellite(int $playerId, SatelliteShape $shape): Satellite
    {
        foreach ($this->satellites->forPlayer($playerId) as $sat) {
            if ($sat->shape === $shape) {
                return $sat;
            }
        }
        throw new \LogicException('satellite not found');
    }

    /** 区域 ID の 1 手先（ニアミスを避けるための事前計算ではなく、期待値の独立した計算に使う） */
    protected function nextCell(int $cell, Direction $dir): int
    {
        $c = $this->geometry()->cellFromId($cell);
        $n = Movement::move($this->geometry(), new SatellitePosition($c->x, $c->y, $dir));
        return $this->geometry()->cellId($n->x, $n->y);
    }

    /** 1 手先が海（または陸）になる区域を探す */
    protected function cellWithNextSea(bool $sea, Direction $dir, int $skip = 0): int
    {
        for ($id = 0; $id < $this->geometry()->cellCount(); $id++) {
            if ($this->layout->areaType($this->nextCell($id, $dir))->isSea() === $sea && $skip-- <= 0) {
                return $id;
            }
        }
        throw new \LogicException('no such cell');
    }
}
