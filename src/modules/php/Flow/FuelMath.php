<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow;

use Bga\Games\ProjectEdgeworth\Material\ProjectCards;
use Bga\Games\ProjectEdgeworth\Material\SatelliteCards;

/** 燃料の表現（001 §4-4）。c は消費量、C は燃料庫の数。 */
final class FuelMath
{
    private function __construct()
    {
    }

    /**
     * 燃料庫の数 C = 衛星カード + 配備されているプロジェクトカード
     *
     * @param list<string> $deployedCardIds
     */
    public static function capacity(string $satelliteCardId, array $deployedCardIds): int
    {
        $sum = SatelliteCards::get($satelliteCardId)->fuelSlots;
        foreach ($deployedCardIds as $id) {
            $sum += ProjectCards::get($id)->fuelSlots;
        }
        return $sum;
    }

    /** 燃料切れ（c > C） */
    public static function isOutOfFuel(int $used, int $capacity): bool
    {
        return $used > $capacity;
    }

    /**
     * 燃料の増減（delta > 0 で消費、< 0 で回復）。0 〜 C + 1 に丸める。
     * 庫外（c > C）の衛星は増減しない（燃料庫に戻らず、それ以上進まない）。
     */
    public static function change(int $used, int $delta, int $capacity): int
    {
        if (self::isOutOfFuel($used, $capacity)) {
            return $capacity + 1;
        }
        return max(0, min($capacity + 1, $used + $delta));
    }

    /**
     * 燃料庫の数が変わったとき（配備・除去・付け替え）の消費量。庫外だった衛星は庫外のまま（U-6）。
     * 庫外は c = C + 1 で保存するため、C が増えると c <= C になって庫内に戻ってしまう。それを防ぐ。
     */
    public static function afterCapacityChange(int $used, int $oldCapacity, int $newCapacity): int
    {
        if (self::isOutOfFuel($used, $oldCapacity)) {
            return $newCapacity + 1;
        }
        return min($used, $newCapacity + 1);
    }
}
