<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Flow;

use Bga\Games\projectedgeworth\Board\AreaType;
use Bga\Games\projectedgeworth\Board\BoardGeometry;
use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Flow\FuelMath;
use Bga\Games\projectedgeworth\Flow\NoOrbitModifiers;
use Bga\Games\projectedgeworth\Flow\OrbitResolver;
use Bga\Games\projectedgeworth\Flow\OrbitSatellite;
use Bga\Games\projectedgeworth\Material\Region;
use Bga\Games\projectedgeworth\Scoring\ScoringBoard;
use PHPUnit\Framework\TestCase;

/** 001 §9 と RB p.18〜19 の例 1〜3・5・6、FAQ の循環・燃料の裁定。2×4 ボード（W = 12、P = 0）。 */
final class OrbitResolverTest extends TestCase
{
    private function geometry(): BoardGeometry
    {
        return BoardGeometry::forBoardCount(4, 0);
    }

    /** @param array<int, AreaType> $types 区域 ID => 種類（指定がなければ標準海） */
    private function board(array $types = []): ScoringBoard
    {
        $g = $this->geometry();
        $all = [];
        $regions = [];
        for ($id = 0; $id < $g->cellCount(); $id++) {
            $all[$id] = $types[$id] ?? AreaType::SEA;
            $regions[$id] = Region::cases()[intdiv($id % $g->columns(), 3)];
        }
        return new ScoringBoard($g, $all, $regions);
    }

    private function sat(int $id, int $x, int $y, Direction $dir, int $used = 0, int $capacity = 2, int $player = 1): OrbitSatellite
    {
        return new OrbitSatellite($id, $player, $this->geometry()->cellId($x, $y), $dir, $used, $capacity);
    }

    /** @return array{int, int, string} */
    private function xyDir(OrbitSatellite $sat): array
    {
        $c = $this->geometry()->cellFromId($sat->cell);
        return [$c->x, $c->y, $sat->dir->value];
    }

    public function testMovementExamples1To3(): void
    {
        $moved = OrbitResolver::move($this->geometry(), [
            $this->sat(1, 23, 1, Direction::NE), // 例 1: 右端 → 左端の 1 つ上の列
            $this->sat(2, 10, 0, Direction::NE), // 例 2: 上端で反射して右下
            $this->sat(3, 23, 5, Direction::SE), // 例 3: 右下端で反射 → 左端へ接続
        ], new NoOrbitModifiers());
        self::assertSame([0, 0, 'NE'], $this->xyDir($moved[0]));
        self::assertSame([11, 1, 'SE'], $this->xyDir($moved[1]));
        self::assertSame([0, 4, 'NE'], $this->xyDir($moved[2]));
    }

    public function testMovementIsSimultaneousAndIndependentOfOrder(): void
    {
        $a = $this->sat(1, 10, 2, Direction::NE);
        $b = $this->sat(2, 12, 0, Direction::SW);
        $m1 = OrbitResolver::move($this->geometry(), [$a, $b], new NoOrbitModifiers());
        $m2 = OrbitResolver::move($this->geometry(), [$b, $a], new NoOrbitModifiers());
        self::assertSame($m1[0]->cell, $m2[1]->cell);
        self::assertSame($m1[1]->cell, $m2[0]->cell);
    }

    public function testFuelConsumptionIsOnePerSatellite(): void
    {
        $after = OrbitResolver::consumeFuel([$this->sat(1, 10, 2, Direction::NE, 0, 5)], new NoOrbitModifiers());
        self::assertSame(1, $after[0]->fuelUsed);
    }

    /** RB p.19 例 5: 同じ区域の 2 機は、それぞれ移動の 1 に加えて 2 消費する。 */
    public function testExample5NearMiss(): void
    {
        $mods = new NoOrbitModifiers();
        $sats = OrbitResolver::move($this->geometry(), [
            $this->sat(1, 10, 2, Direction::NE, 0, 5, 2), // SCALLOP
            $this->sat(2, 12, 0, Direction::SW, 0, 5, 3), // HAMMER
            $this->sat(3, 0, 2, Direction::NE, 0, 5, 3),  // 別の区域
        ], $mods);
        self::assertSame($sats[0]->cell, $sats[1]->cell);
        $sats = OrbitResolver::consumeFuel($sats, $mods);
        $ids = OrbitResolver::nearMissIds($sats, $mods);
        self::assertSame([1, 2], $ids);
        $sats = OrbitResolver::applyNearMiss($sats, $ids, $mods);
        self::assertSame([3, 3, 1], array_map(static fn(OrbitSatellite $s): int => $s->fuelUsed, $sats));
    }

    /** FAQ 行 2: 同じプレイヤーの複数の衛星も、同じ区域ならニアミス。3 機以上は全機。 */
    public function testSamePlayerAndThreeSatellites(): void
    {
        $mods = new NoOrbitModifiers();
        $cell = $this->geometry()->cellId(11, 1);
        $sats = [
            new OrbitSatellite(1, 1, $cell, Direction::NE, 0, 5),
            new OrbitSatellite(2, 1, $cell, Direction::SE, 0, 5),
            new OrbitSatellite(3, 2, $cell, Direction::NW, 0, 5),
            new OrbitSatellite(4, 2, $cell + 1, Direction::NW, 0, 5),
        ];
        self::assertSame([1, 2, 3], OrbitResolver::nearMissIds($sats, $mods));
    }

    /** RB p.19 例 6: 燃料切れの CONCORDE も着陸判定の前なのでニアミスに数え、HAMMER は計 3 消費。CONCORDE は海に着水して 10 マヤ。 */
    public function testExample6OutOfFuelStillCountsForNearMiss(): void
    {
        $mods = new NoOrbitModifiers();
        $sats = OrbitResolver::move($this->geometry(), [
            $this->sat(1, 10, 2, Direction::NE, 2, 2, 1),  // CONCORDE: 容量 2・消費 2
            $this->sat(2, 12, 0, Direction::SW, 0, 5, 2),  // HAMMER
        ], $mods);
        $sats = OrbitResolver::consumeFuel($sats, $mods);
        self::assertTrue($sats[0]->isOutOfFuel(), '移動の燃料消費で燃料切れ');
        self::assertSame(1, $sats[1]->fuelUsed);

        $ids = OrbitResolver::nearMissIds($sats, $mods);
        self::assertSame([1, 2], $ids);
        $sats = OrbitResolver::applyNearMiss($sats, $ids, $mods);
        self::assertSame(3, $sats[1]->fuelUsed, 'HAMMER はこの循環で計 3 消費');
        self::assertSame(3, $sats[0]->fuelUsed, '燃料切れは C + 1 で止まる');

        $landings = OrbitResolver::landings($sats, $this->board());
        self::assertCount(1, $landings);
        self::assertSame(1, $landings[0]->satId);
        self::assertTrue($landings[0]->sea);
        self::assertSame(10, $landings[0]->maya);
    }

    /** 001 §9-4、FAQ 行 41: 陸区域では 10 マヤを得ない。 */
    public function testLandOnLandGivesNoMaya(): void
    {
        $cell = $this->geometry()->cellId(11, 1);
        $sat = new OrbitSatellite(1, 1, $cell, Direction::NE, 3, 2);
        $landings = OrbitResolver::landings([$sat], $this->board([$cell => AreaType::LAND]));
        self::assertFalse($landings[0]->sea);
        self::assertSame(0, $landings[0]->maya);
        // 深海・岩礁も海区域
        $landings = OrbitResolver::landings([$sat], $this->board([$cell => AreaType::DEEP_SEA]));
        self::assertSame(10, $landings[0]->maya);
    }

    /** FAQ 行 12・59: 庫外に出ただけでは着陸判定にならず、燃料が残っていれば着陸しない。 */
    public function testNoLandingWhileFuelRemains(): void
    {
        $cell = $this->geometry()->cellId(11, 1);
        $edge = new OrbitSatellite(1, 1, $cell, Direction::NE, 2, 2); // c = C は庫内
        self::assertSame([], OrbitResolver::landings([$edge], $this->board()));
    }

    /** FAQ 行 14: 着陸判定前に庫外にいた衛星は、回復する効果があっても庫内に戻らず着陸する。 */
    public function testRecoveryDoesNotBringBack(): void
    {
        $mods = new class extends NoOrbitModifiers {
            public function entryFuelDelta(OrbitSatellite $sat): int
            {
                return -2;
            }
        };
        $sat = $this->sat(1, 10, 2, Direction::NE, 7, 6);
        $after = OrbitResolver::consumeFuel([$sat], $mods);
        self::assertTrue($after[0]->isOutOfFuel());
        self::assertSame(7, $after[0]->fuelUsed);
    }

    /** FAQ 行 33: 燃料消費は移動後の位置で問い合わせる。 */
    public function testFuelCostIsQueriedAfterMove(): void
    {
        $geometry = $this->geometry();
        $dest = $geometry->cellId(11, 1);
        $mods = new class ($dest) extends NoOrbitModifiers {
            public function __construct(private readonly int $free)
            {
            }

            public function moveFuelCost(OrbitSatellite $sat): int
            {
                return $sat->cell === $this->free ? 0 : 1;
            }
        };
        $moved = OrbitResolver::move($geometry, [$this->sat(1, 10, 2, Direction::NE, 0, 5)], $mods);
        self::assertSame($dest, $moved[0]->cell);
        self::assertSame(0, OrbitResolver::consumeFuel($moved, $mods)[0]->fuelUsed);
    }

    /** FAQ 行 34・4: 追加移動の途中ではニアミスしない。最終位置だけで判定する。 */
    public function testExtraMoveHasNoNearMissAlongTheWay(): void
    {
        $geometry = $this->geometry();
        $mods = new class extends NoOrbitModifiers {
            public function extraMoves(OrbitSatellite $sat): int
            {
                return $sat->satId === 1 ? 1 : 0;
            }
        };
        // 衛星 1 は 2 区域進み (12, 0) の手前の (11, 1) を通過、衛星 2 は (11, 1) に止まる
        $sats = OrbitResolver::move($geometry, [
            $this->sat(1, 10, 2, Direction::NE),
            $this->sat(2, 12, 0, Direction::SW),
        ], $mods);
        self::assertSame([12, 0, 'NE'], $this->xyDir($sats[0]));
        self::assertSame($geometry->cellId(11, 1), $sats[1]->cell);
        self::assertSame([], OrbitResolver::nearMissIds($sats, $mods));
    }

    /** 007 §5-5（P085）: ニアミスの消費は最初から 1 として扱い、庫外に出るかは 1 消費した結果で判定する。 */
    public function testNearMissCostOfOne(): void
    {
        $mods = new class extends NoOrbitModifiers {
            public function nearMissFuelCost(OrbitSatellite $sat): int
            {
                return 1;
            }
        };
        $sat = new OrbitSatellite(1, 1, 5, Direction::NE, 1, 2);
        self::assertSame(2, OrbitResolver::applyNearMiss([$sat], [1], $mods)[0]->fuelUsed);
        self::assertFalse(OrbitResolver::applyNearMiss([$sat], [1], $mods)[0]->isOutOfFuel());
        $sat = $sat->withFuelUsed(2);
        self::assertTrue(OrbitResolver::applyNearMiss([$sat], [1], $mods)[0]->isOutOfFuel());
    }

    /** FAQ 行 58（P094）: 2 機だけなら相手もニアミスしない。3 機なら免除の衛星以外はニアミスする。 */
    public function testNearMissExemption(): void
    {
        $mods = new class extends NoOrbitModifiers {
            public function nearMissExempt(OrbitSatellite $sat): bool
            {
                return $sat->satId === 1;
            }
        };
        $mk = static fn(int $id): OrbitSatellite => new OrbitSatellite($id, 1, 7, Direction::NE, 0, 2);
        self::assertSame([], OrbitResolver::nearMissIds([$mk(1), $mk(2)], $mods));
        self::assertSame([2, 3], OrbitResolver::nearMissIds([$mk(1), $mk(2), $mk(3)], $mods));
        self::assertSame([2, 3], OrbitResolver::nearMissIds([$mk(2), $mk(3)], $mods));
    }

    public function testDefaultModifiersChangeNothing(): void
    {
        $mods = new NoOrbitModifiers();
        $sat = $this->sat(1, 10, 2, Direction::NE);
        self::assertSame(0, $mods->extraMoves($sat));
        self::assertSame(0, $mods->entryFuelDelta($sat));
        self::assertSame(1, $mods->moveFuelCost($sat));
        self::assertFalse($mods->nearMissExempt($sat));
        self::assertSame(2, $mods->nearMissFuelCost($sat));
        self::assertSame(3, FuelMath::change(0, $mods->moveFuelCost($sat) + $mods->nearMissFuelCost($sat), 5));
    }
}
