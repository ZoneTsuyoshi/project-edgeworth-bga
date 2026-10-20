<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow;

use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Flow\NoOrbitModifiers;
use Bga\Games\projectedgeworth\Flow\OrbitReport;
use Bga\Games\projectedgeworth\Flow\OrbitSatellite;
use Bga\Games\projectedgeworth\Material\SatelliteShape;

/** 009 の 100 OrbitDriver と 001 §9 を DB 込みで確かめる。 */
final class OrbitPhaseTest extends FlowTestCase
{
    public function testNoActiveSatelliteDoesNothing(): void
    {
        $report = $this->orbit->run($this->geometry(), $this->scoringBoard());
        self::assertSame([], $report->nearMissIds);
        self::assertSame([], $report->landings);
    }

    /** FAQ 行 38: 打ち上げたばかりの衛星も移動し、燃料を 1 消費する。 */
    public function testNewlyLaunchedSatelliteMovesAndConsumesFuel(): void
    {
        $cell = $this->cellWithNextSea(true, Direction::NE);
        $sat = $this->satellite(101, SatelliteShape::Hammer);
        $this->satellites->launch($sat->id, $cell, Direction::NE);

        $this->orbit->run($this->geometry(), $this->scoringBoard());

        $after = $this->satellites->get($sat->id);
        self::assertSame($this->nextCell($cell, Direction::NE), $after->cell);
        self::assertSame(1, $after->fuelUsed);
        self::assertTrue($after->isActive());
    }

    public function testNonActiveSatellitesAreUntouched(): void
    {
        $sat = $this->satellite(101, SatelliteShape::Retro);
        $this->orbit->run($this->geometry(), $this->scoringBoard());
        $after = $this->satellites->get($sat->id);
        self::assertFalse($after->isActive());
        self::assertSame(0, $after->fuelUsed);
    }

    /** RB p.19 例 5・6 を DB で。燃料切れでもニアミスに数え、着水で 10 マヤ。 */
    public function testNearMissThenSplashDown(): void
    {
        $dest = $this->cellWithNextSea(true, Direction::NE);
        $destCell = $this->nextCell($dest, Direction::NE);
        $g = $this->geometry();
        $dc = $g->cellFromId($destCell);
        // 衛星 2 の出発点: 行き先 destCell に向かう南西向きの 1 手前（上端行は避ける）
        $fromB = null;
        foreach ([Direction::SW, Direction::SE, Direction::NW] as $d) {
            $opp = $d->opposite();
            $x = $dc->x + $opp->dx();
            $y = $dc->y + $opp->dy();
            if ($g->inRows($y) && $g->wrapX($x) !== null && $y !== 0 && $y !== 5) {
                $fromB = [$g->cellId($x, $y), $d];
                break;
            }
        }
        self::assertNotNull($fromB);

        $concorde = $this->satellite(101, SatelliteShape::Concorde);
        $hammer = $this->satellite(102, SatelliteShape::Hammer);
        $this->satellites->launch($concorde->id, $dest, Direction::NE);
        $this->satellites->addFuelUsed($concorde->id, 2, 2); // 容量 2 を使い切った状態（c = C）
        $this->satellites->launch($hammer->id, $fromB[0], $fromB[1]);
        $mayaBefore = $this->playerValues->maya(101);
        $otherBefore = $this->playerValues->maya(102);

        $mods = new NoOrbitModifiers();
        $this->orbit->stepMove($g, $mods);
        $this->orbit->stepFuel($mods);
        $nearMiss = $this->orbit->stepNearMiss($mods);
        self::assertSame([$concorde->id, $hammer->id], $nearMiss);
        // HAMMER（容量 2）はこの循環で計 3 消費して庫外。CONCORDE は庫外のまま増えない
        self::assertSame(3, $this->satellites->get($hammer->id)->fuelUsed);
        self::assertSame(3, $this->satellites->get($concorde->id)->fuelUsed);
        $landings = $this->orbit->stepLanding($this->scoringBoard());
        $this->orbit->finish();
        $report = new OrbitReport($nearMiss, $landings);

        self::assertFalse($this->satellites->get($concorde->id)->isActive());
        self::assertSame(0, $this->satellites->get($concorde->id)->fuelUsed, '着水で燃料は初期燃料庫に戻る');
        self::assertCount(2, $report->landings, 'HAMMER も庫外なので同じ判定で着陸する');
        self::assertSame(10, $report->landings[0]->maya);
        self::assertSame($mayaBefore + 10, $this->playerValues->maya(101));
        self::assertSame($otherBefore + $report->landings[1]->maya, $this->playerValues->maya(102));
        self::assertFalse($this->satellites->get($concorde->id)->grace, 'finish で猶予は外れる');
    }

    /** FAQ 行 41: 陸区域では 10 マヤを得ない。 */
    public function testLandingOnLandGivesNoMaya(): void
    {
        $cell = $this->cellWithNextSea(false, Direction::NE);
        $sat = $this->satellite(101, SatelliteShape::Hammer);
        $this->satellites->launch($sat->id, $cell, Direction::NE);
        $this->satellites->addFuelUsed($sat->id, 2, 2);
        $before = $this->playerValues->maya(101);

        $report = $this->orbit->run($this->geometry(), $this->scoringBoard());

        self::assertFalse($report->landings[0]->sea);
        self::assertFalse($this->satellites->get($sat->id)->isActive());
        self::assertSame($before, $this->playerValues->maya(101));
    }

    /** FAQ 行 32: 着陸した衛星は O4 の窓が閉じるまで猶予（grace）が付き、finish で外れる。 */
    public function testGraceUntilFinish(): void
    {
        $cell = $this->cellWithNextSea(true, Direction::NE);
        $sat = $this->satellite(101, SatelliteShape::Hammer);
        $this->satellites->launch($sat->id, $cell, Direction::NE);
        $this->satellites->addFuelUsed($sat->id, 2, 2);

        $this->orbit->stepMove($this->geometry(), new NoOrbitModifiers());
        $this->orbit->stepFuel(new NoOrbitModifiers());
        $this->orbit->stepNearMiss(new NoOrbitModifiers());
        $this->orbit->stepLanding($this->scoringBoard());
        self::assertTrue($this->satellites->get($sat->id)->grace);
        self::assertFalse($this->satellites->get($sat->id)->isActive());
        $this->orbit->finish();
        self::assertFalse($this->satellites->get($sat->id)->grace);
    }

    /** 燃料庫は衛星カード + 配備されたカード。庫内の消費では着陸しない。 */
    public function testDeployedCardsExtendCapacity(): void
    {
        $cell = $this->cellWithNextSea(true, Direction::NE);
        $sat = $this->satellite(101, SatelliteShape::Hammer);
        $this->satellites->launch($sat->id, $cell, Direction::NE);
        // P004（燃料庫 2）を手札から配備 → 容量 4
        $hand = $this->cards->idsIn(CardLocation::Hand, 101);
        $this->db->execute('UPDATE `project_card` SET `card_location` = \'hand\', `card_location_arg` = 101 WHERE `card_id` = 4');
        $this->cards->deploy(101, 4, $sat->id);
        self::assertNotEmpty($hand);
        $this->satellites->addFuelUsed($sat->id, 2, 4);

        $report = $this->orbit->run($this->geometry(), $this->scoringBoard());

        self::assertSame([], $report->landings);
        self::assertSame(3, $this->satellites->get($sat->id)->fuelUsed);
        $active = $this->orbit->activeSatellites();
        self::assertCount(1, $active);
        self::assertInstanceOf(OrbitSatellite::class, $active[0]);
        self::assertSame(4, $active[0]->capacity);
    }

    /** FAQ 行 13・U-6: 庫外の衛星は消費が増えない。次の判定で着陸する。 */
    public function testOutOfFuelSatelliteStaysAtCapacityPlusOne(): void
    {
        $cell = $this->cellWithNextSea(true, Direction::NE);
        $sat = $this->satellite(101, SatelliteShape::Hammer);
        $this->satellites->launch($sat->id, $cell, Direction::NE);
        $this->satellites->addFuelUsed($sat->id, 3, 2);
        self::assertSame(3, $this->satellites->get($sat->id)->fuelUsed);

        $this->orbit->stepMove($this->geometry(), new NoOrbitModifiers());
        $this->orbit->stepFuel(new NoOrbitModifiers());
        self::assertSame(3, $this->satellites->get($sat->id)->fuelUsed);
        $this->orbit->stepNearMiss(new NoOrbitModifiers());
        self::assertSame(3, $this->satellites->get($sat->id)->fuelUsed);
        self::assertCount(1, $this->orbit->stepLanding($this->scoringBoard()));
    }
}
