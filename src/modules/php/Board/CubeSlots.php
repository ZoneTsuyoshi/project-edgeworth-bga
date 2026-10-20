<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/** キューブ置き場の Z 順（003 §5）。0 = 左上、1 = 右上、2 = 左下、3 = 右下。 */
final class CubeSlots
{
    public const SLOT_COUNT = 4;

    private function __construct()
    {
    }

    /**
     * 置くマス。区域で埋まっているマスの数 n（キューブは重ねた分も 1 つずつ、発射拠点コマは slot 0 を占めるので 1 つ）に対し slot n。
     * 空きがなければ null。
     */
    public static function nextSlot(int $occupiedCount): ?int
    {
        return $occupiedCount >= 0 && $occupiedCount < self::SLOT_COUNT ? $occupiedCount : null;
    }
}
