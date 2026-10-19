<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Board;

/** キューブ置き場の Z 順（003 §5）。0 = 左上、1 = 右上、2 = 左下、3 = 右下。 */
final class CubeSlots
{
    public const SLOT_COUNT = 4;

    private function __construct()
    {
    }

    /**
     * 置くマス。区域にあるキューブ数 n（重ねた分も 1 つずつ数える）に対し slot n。
     * 空きがなければ null。
     */
    public static function nextSlot(int $existingCubeCount): ?int
    {
        return $existingCubeCount >= 0 && $existingCubeCount < self::SLOT_COUNT ? $existingCubeCount : null;
    }
}
