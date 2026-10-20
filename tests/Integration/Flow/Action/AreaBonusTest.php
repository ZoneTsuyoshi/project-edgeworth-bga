<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Action;

use Bga\Games\projectedgeworth\Board\Adjacency;
use Bga\Games\projectedgeworth\Board\AreaType;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;

/**
 * 区域ボーナス（001 §8、裏面）と岩礁の割引（U-13）。裏面の Earth 配置を使う。
 */
final class AreaBonusTest extends ActionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->startGame(3, true);
        foreach ($this->ids as $id) {
            $this->setMaya($id, 40);
        }
    }

    /** @return array<string, mixed> */
    private function link(int $cell, int $satId): array
    {
        return ['op' => 'link', 'cell' => $cell, 'sat' => $satId, 'sell' => []];
    }

    private function place(int $playerId, int $index, int $cell): int
    {
        $sat = $this->sat($playerId, $index);
        $this->activate($sat->id, $cell);
        return $sat->id;
    }

    public function testForestGivesFiveMayaOnlyToTheFirstLinker(): void
    {
        $forest = $this->cellOf(AreaType::FOREST);
        $a = $this->place(101, 0, $forest);
        $b = $this->place(102, 0, $forest);
        $link = $this->phases->link();

        $result = $link->resolve(StepMode::Main, [101 => $this->link($forest, $a)]);
        $this->assertSame(5, $result->outcomeOf(101)?->details['bonus']->maya);
        $this->assertSame(40, $this->maya(101));

        // 2 番目（別のコミット）にはボーナスがない。先駆コストを 101 に払う
        $result = $link->resolve(StepMode::Bonus, [102 => $this->link($forest, $b)]);
        $this->assertSame(0, $result->outcomeOf(102)?->details['bonus']->maya);
        $this->assertSame(40 - 5 - 3, $this->maya(102));
        $this->assertSame(43, $this->maya(101));
    }

    public function testLaunchBaseCellGivesNoTopBonusToAnyone(): void
    {
        // 発射拠点は slot 0 を占める（作者回答 2026-10-20）: 最初の通信確立でも 2 番目の扱い
        $forest = $this->cellOf(AreaType::FOREST);
        $mountain = $this->cellOf(AreaType::MOUNTAIN);
        $this->pad(103, $forest);
        $this->pad(102, $mountain);
        $a = $this->place(101, 0, $forest);
        $b = $this->place(101, 1, $mountain);
        $handBefore = count($this->handOf(101));
        $link = $this->phases->link();

        $result = $link->resolve(StepMode::Main, [101 => $this->link($forest, $a)]);
        $this->assertSame(0, $result->outcomeOf(101)?->details['bonus']->maya);
        $this->assertSame(40 - 5 - 3, $this->maya(101));
        $this->assertSame(1, $this->phases->services()->links->inCell($forest)[0]->slot);

        $result = $link->resolve(StepMode::Bonus, [101 => $this->link($mountain, $b)]);
        $this->assertSame([], $result->outcomeOf(101)?->details['bonus']->drawn);
        $this->assertCount($handBefore, $this->handOf(101));
    }

    public function testDeepSeaOnALaunchBaseCellRecoversOneFuelForTheFirstLinker(): void
    {
        $deep = $this->cellOf(AreaType::DEEP_SEA);
        $satellites = $this->phases->services()->satellites;
        $this->pad(103, $deep);
        $a = $this->place(101, 0, $deep);
        $satellites->addFuelUsed($a, 2, 2);
        $first = $this->phases->link()->resolve(StepMode::Main, [101 => $this->link($deep, $a)]);
        $this->assertSame(1, $first->outcomeOf(101)?->details['bonus']->fuelRecovered);
        $this->assertSame(1, $satellites->get($a)->fuelUsed);
    }

    public function testSimultaneousLinkersAllGetTheTopBonus(): void
    {
        $forest = $this->cellOf(AreaType::FOREST);
        $a = $this->place(101, 0, $forest);
        $b = $this->place(102, 0, $forest);
        $this->phases->link()->resolve(StepMode::Main, [101 => $this->link($forest, $a), 102 => $this->link($forest, $b)]);
        $this->assertSame(40, $this->maya(101));
        $this->assertSame(40, $this->maya(102));
    }

    public function testSnowGivesOneMayaToEveryLinker(): void
    {
        $snow = $this->cellOf(AreaType::SNOW);
        $a = $this->place(101, 0, $snow);
        $b = $this->place(102, 0, $snow);
        $link = $this->phases->link();
        $link->resolve(StepMode::Main, [101 => $this->link($snow, $a)]);
        $this->assertSame(40 - 5 + 1, $this->maya(101));
        $link->resolve(StepMode::Bonus, [102 => $this->link($snow, $b)]);
        // 2 番目も +1 マヤ（順位を問わない）。先駆コスト 3 を払う
        $this->assertSame(40 - 5 - 3 + 1, $this->maya(102));
    }

    public function testMountainDrawsOneCardForTheFirstLinkerOnly(): void
    {
        $mountain = $this->cellOf(AreaType::MOUNTAIN);
        $a = $this->place(101, 0, $mountain);
        $b = $this->place(102, 0, $mountain);
        $link = $this->phases->link();
        $handA = count($this->handOf(101));
        $handB = count($this->handOf(102));

        $first = $link->resolve(StepMode::Main, [101 => $this->link($mountain, $a)]);
        $this->assertCount(1, $first->outcomeOf(101)?->details['bonus']->drawn);
        $this->assertCount($handA + 1, $this->handOf(101));
        $link->resolve(StepMode::Bonus, [102 => $this->link($mountain, $b)]);
        $this->assertCount($handB, $this->handOf(102));
    }

    public function testDeepSeaRecoversFuelTwoThenOneAndNeverBelowTheStart(): void
    {
        $deep = $this->cellOf(AreaType::DEEP_SEA);
        $satellites = $this->phases->services()->satellites;
        $a = $this->place(101, 0, $deep);
        $b = $this->place(102, 0, $deep);
        $c = $this->place(103, 0, $deep);
        $satellites->addFuelUsed($a, 2, 2);
        $satellites->addFuelUsed($b, 2, 2);
        $satellites->addFuelUsed($c, 1, 2);
        $link = $this->phases->link();

        $first = $link->resolve(StepMode::Main, [101 => $this->link($deep, $a)]);
        $this->assertSame(2, $first->outcomeOf(101)?->details['bonus']->fuelRecovered);
        $this->assertSame(0, $satellites->get($a)->fuelUsed);

        $second = $link->resolve(StepMode::Bonus, [102 => $this->link($deep, $b)]);
        $this->assertSame(1, $second->outcomeOf(102)?->details['bonus']->fuelRecovered);
        $this->assertSame(1, $satellites->get($b)->fuelUsed);

        // 3 番目以降はボーナスなし
        $third = $link->resolve(StepMode::Bonus, [103 => $this->link($deep, $c)]);
        $this->assertSame(0, $third->outcomeOf(103)?->details['bonus']->fuelRecovered);
        $this->assertSame(1, $satellites->get($c)->fuelUsed);
    }

    public function testDeepSeaRecoveryStopsAtTheInitialFuelTankAndSkipsEmptySatellites(): void
    {
        $deep = $this->cellOf(AreaType::DEEP_SEA);
        $satellites = $this->phases->services()->satellites;
        $a = $this->place(101, 0, $deep);
        $b = $this->place(102, 0, $deep);
        $satellites->addFuelUsed($a, 1, 2);
        // 燃料切れ（消費 > 容量 2）の衛星は回復で動かせない
        $satellites->addFuelUsed($b, 5, 2);
        $this->assertSame(3, $satellites->get($b)->fuelUsed);

        $first = $this->phases->link()->resolve(StepMode::Main, [101 => $this->link($deep, $a)]);
        $this->assertSame(1, $first->outcomeOf(101)?->details['bonus']->fuelRecovered);
        $second = $this->phases->link()->resolve(StepMode::Main, [102 => $this->link($deep, $b)]);
        $this->assertSame(0, $second->outcomeOf(102)?->details['bonus']->fuelRecovered);
        $this->assertSame(3, $satellites->get($b)->fuelUsed);
    }

    public function testDeployedFuelTanksRaiseTheCapacity(): void
    {
        $sat = $this->sat(101, 0);
        // 衛星カード 2 + P003（燃料庫 3）+ P026（4）
        $this->deployDirect(101, 3, $sat->id);
        $this->deployDirect(101, 26, $sat->id);
        $this->assertSame(2 + 3 + 4, $this->phases->services()->fuelCapacity($sat->id));
    }

    public function testPlainAreasGiveNoBonus(): void
    {
        $plain = $this->cellOf(AreaType::SEA);
        $a = $this->place(101, 0, $plain);
        $result = $this->phases->link()->resolve(StepMode::Main, [101 => $this->link($plain, $a)]);
        $bonus = $result->outcomeOf(101)?->details['bonus'];
        $this->assertSame(0, $bonus->maya);
        $this->assertSame([], $bonus->drawn);
        $this->assertSame(0, $bonus->fuelRecovered);
    }

    /** @return array{0: int, 1: int}|null 岩礁と、それに隣接する陸区域 */
    private function reefWithAdjacentLand(): ?array
    {
        $geometry = $this->layout->geometry();
        foreach ($this->layout->cellIds() as $cell) {
            if ($this->layout->areaType($cell) !== AreaType::REEF) {
                continue;
            }
            $here = $geometry->cellFromId($cell);
            foreach (Adjacency::neighbors($geometry, $here->x, $here->y) as $near) {
                $id = $geometry->cellId($near->x, $near->y);
                if ($this->layout->areaType($id)->isLand()) {
                    return [$cell, $id];
                }
            }
        }
        return null;
    }

    public function testReefLowersTheLandCostByOneOnlyForItsOwnLinker(): void
    {
        $pair = $this->reefWithAdjacentLand();
        $this->assertNotNull($pair);
        [$reef, $land] = $pair;
        $sat101 = $this->place(101, 0, $land);
        $sat102 = $this->place(102, 0, $land);
        $links = $this->phases->services()->links;
        $links->commit([['playerId' => 101, 'cell' => $reef]], 1, $this->globals->incCommitSeq());
        $link = $this->phases->link();
        $satellites = $this->phases->services()->satellites;

        $this->assertSame(4, $link->cost(101, $satellites->get($sat101), $land)->areaCost);
        $this->assertSame(1, $link->cost(101, $satellites->get($sat101), $land)->reefDiscount);
        $this->assertSame(5, $link->cost(102, $satellites->get($sat102), $land)->areaCost);
    }

    public function testReefDiscountDoesNotStackAndDoesNotApplyToSea(): void
    {
        $geometry = $this->layout->geometry();
        $reefs = [];
        foreach ($this->layout->cellIds() as $cell) {
            if ($this->layout->areaType($cell) === AreaType::REEF) {
                $reefs[] = $cell;
            }
        }
        // 岩礁が 2 つ以上隣接する陸区域を探す
        $target = null;
        foreach ($this->layout->cellIds() as $cell) {
            if (!$this->layout->areaType($cell)->isLand()) {
                continue;
            }
            $here = $geometry->cellFromId($cell);
            $near = [];
            foreach (Adjacency::neighbors($geometry, $here->x, $here->y) as $n) {
                $id = $geometry->cellId($n->x, $n->y);
                if (in_array($id, $reefs, true)) {
                    $near[] = $id;
                }
            }
            if (count($near) >= 2) {
                $target = [$cell, $near];
                break;
            }
        }
        $this->assertNotNull($target);
        [$land, $near] = $target;
        $sat = $this->place(101, 0, $land);
        foreach ($near as $reef) {
            $this->phases->services()->links->commit([['playerId' => 101, 'cell' => $reef]], 1, $this->globals->incCommitSeq());
        }
        $cost = $this->phases->link()->cost(101, $this->phases->services()->satellites->get($sat), $land);
        $this->assertSame(4, $cost->areaCost);

        // 海区域の区域コストは変わらない
        $sea = $this->cellOf(AreaType::SEA);
        $this->assertSame(2, $this->phases->link()->cost(101, $this->phases->services()->satellites->get($sat), $sea)->areaCost);
    }
}
