<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Query;

use Bga\Games\ProjectEdgeworth\Flow\NoOrbitModifiers;
use Bga\Games\ProjectEdgeworth\Flow\OrbitSatellite;

/**
 * 効果エンジンにつないだ `OrbitModifiers`（`NoOrbitModifiers` の差し替え。011 §3-2）。
 *
 * `FUEL_CONSUME`（O2）と `NEAR_MISS`（O3）は問い合わせフックなので、ここで答える。一方 `extraMoves`（P081・P082・P091 の追加移動）と
 * `entryFuelDelta`（P083・P084 の燃料回復）は、011 では窓フック `ORBIT_ENTERED`（O1）の結果部品（R07・R05）で、プレイヤーの
 * [Use] / [Skip] を聞く効果なので同期の問い合わせでは表せない。`OrbitDriver`（状態 100）が O1 の窓を開くときにこの 2 つは
 * 使わなくなる。それまでは `NoOrbitModifiers` のまま（0）を返す。
 */
final class EngineOrbitModifiers extends NoOrbitModifiers
{
    public function __construct(private readonly EffectQueries $queries)
    {
    }

    public function moveFuelCost(OrbitSatellite $sat): int
    {
        return $this->queries->fuelConsume($this->context($sat), self::MOVE_FUEL);
    }

    public function nearMissExempt(OrbitSatellite $sat): bool
    {
        return $this->queries->nearMiss($this->context($sat), new NearMissAdjust(false, self::NEAR_MISS_FUEL))->exempt;
    }

    public function nearMissFuelCost(OrbitSatellite $sat): int
    {
        return $this->queries->nearMiss($this->context($sat), new NearMissAdjust(false, self::NEAR_MISS_FUEL))->fuel;
    }

    private function context(OrbitSatellite $sat): QueryContext
    {
        return new QueryContext($sat->playerId, $sat->satId, cell: $sat->cell);
    }
}
