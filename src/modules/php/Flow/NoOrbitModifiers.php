<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow;

/** 修正なし（001 §9 の基本ルールのまま）。一部だけ変えるときは継承して上書きする。 */
class NoOrbitModifiers implements OrbitModifiers
{
    public const MOVE_FUEL = 1;
    public const NEAR_MISS_FUEL = 2;

    public function extraMoves(OrbitSatellite $sat): int
    {
        return 0;
    }

    public function entryFuelDelta(OrbitSatellite $sat): int
    {
        return 0;
    }

    public function moveFuelCost(OrbitSatellite $sat): int
    {
        return self::MOVE_FUEL;
    }

    public function nearMissExempt(OrbitSatellite $sat): bool
    {
        return false;
    }

    public function nearMissFuelCost(OrbitSatellite $sat): int
    {
        return self::NEAR_MISS_FUEL;
    }
}
