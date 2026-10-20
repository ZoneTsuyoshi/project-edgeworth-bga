<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow;

use Bga\Games\projectedgeworth\Board\BoardGeometry;
use Bga\Games\projectedgeworth\Board\Movement;
use Bga\Games\projectedgeworth\Board\SatellitePosition;
use Bga\Games\projectedgeworth\Scoring\ScoringBoard;

/**
 * 循環フェイズの各ステップの計算（001 §9、007 §4・§5-6）。DB に依存しない純粋関数。
 * 状態機械は O0 → move → consumeFuel → nearMiss → landings → O4 の順に呼ぶ。
 */
final class OrbitResolver
{
    /** 海区域への着水で得るマヤ */
    public const SEA_LANDING_MAYA = 10;

    private function __construct()
    {
    }

    /**
     * 衛星コマの移動。全衛星を同時に 1 区域進め、追加移動（O1）の分をさらに進める。
     * 位置は互いに独立なので、順序は結果に影響しない。
     *
     * @param list<OrbitSatellite> $sats
     * @return list<OrbitSatellite>
     */
    public static function move(BoardGeometry $geometry, array $sats, OrbitModifiers $mods): array
    {
        $result = [];
        foreach ($sats as $sat) {
            $pos = new SatellitePosition($geometry->cellFromId($sat->cell)->x, $geometry->cellFromId($sat->cell)->y, $sat->dir);
            for ($i = 1 + max(0, $mods->extraMoves($sat)); $i > 0; $i--) {
                $pos = Movement::move($geometry, $pos);
            }
            $result[] = $sat->withPosition($geometry->cellId($pos->x, $pos->y), $pos->dir);
        }
        return $result;
    }

    /**
     * 燃料消費。移動直後の燃料の増減（O1）を先に反映し、その後に移動分（O2）を消費する。
     * 判定は移動後の位置で行う（FAQ 行 33）。着陸はまだしない（FAQ 行 12・59）。
     *
     * @param list<OrbitSatellite> $sats
     * @return list<OrbitSatellite>
     */
    public static function consumeFuel(array $sats, OrbitModifiers $mods): array
    {
        $result = [];
        foreach ($sats as $sat) {
            $used = FuelMath::change($sat->fuelUsed, $mods->entryFuelDelta($sat), $sat->capacity);
            $used = FuelMath::change($used, max(0, $mods->moveFuelCost($sat)), $sat->capacity);
            $result[] = $sat->withFuelUsed($used);
        }
        return $result;
    }

    /**
     * ニアミスする衛星の ID。全衛星の移動後の位置で一斉に判定する。
     * 同じ区域に 2 機以上いればニアミス（同じプレイヤー同士も。燃料切れの衛星も数える。FAQ 行 2、RB p.19 例 6）。
     * ニアミスしない衛星（P094）はその区域で除外し、その区域が 2 機だけなら相手もニアミスしない（FAQ 行 58）。
     *
     * @param list<OrbitSatellite> $sats
     * @return list<int> sat_id の昇順
     */
    public static function nearMissIds(array $sats, OrbitModifiers $mods): array
    {
        $byCell = [];
        foreach ($sats as $sat) {
            $byCell[$sat->cell][] = $sat;
        }
        $ids = [];
        foreach ($byCell as $group) {
            if (count($group) < 2) {
                continue;
            }
            $exempt = false;
            foreach ($group as $sat) {
                $exempt = $exempt || $mods->nearMissExempt($sat);
            }
            if ($exempt && count($group) === 2) {
                continue;
            }
            foreach ($group as $sat) {
                if (!$mods->nearMissExempt($sat)) {
                    $ids[] = $sat->satId;
                }
            }
        }
        sort($ids);
        return $ids;
    }

    /**
     * ニアミスした衛星の燃料を消費する（全員同時）。
     *
     * @param list<OrbitSatellite> $sats
     * @param list<int> $nearMissIds
     * @return list<OrbitSatellite>
     */
    public static function applyNearMiss(array $sats, array $nearMissIds, OrbitModifiers $mods): array
    {
        $result = [];
        foreach ($sats as $sat) {
            if (in_array($sat->satId, $nearMissIds, true)) {
                $sat = $sat->withFuelUsed(
                    FuelMath::change($sat->fuelUsed, max(0, $mods->nearMissFuelCost($sat)), $sat->capacity),
                );
            }
            $result[] = $sat;
        }
        return $result;
    }

    /**
     * 着陸 / 着水判定。燃料切れ（c > C）の衛星が対象。海区域なら持ち主に 10 マヤ。
     *
     * @param list<OrbitSatellite> $sats
     * @return list<Landing> sat_id の昇順
     */
    public static function landings(array $sats, ScoringBoard $board): array
    {
        $result = [];
        foreach ($sats as $sat) {
            if (!$sat->isOutOfFuel()) {
                continue;
            }
            $sea = $board->areaType($sat->cell)->isSea();
            $result[] = new Landing($sat->satId, $sat->playerId, $sat->cell, $sea, $sea ? self::SEA_LANDING_MAYA : 0);
        }
        usort($result, static fn(Landing $a, Landing $b): int => $a->satId <=> $b->satId);
        return $result;
    }
}
