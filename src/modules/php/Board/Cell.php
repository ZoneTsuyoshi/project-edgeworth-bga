<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/** ダブルド座標 (x, y) の区域（003 §1.2）。 */
final class Cell
{
    public function __construct(
        public readonly int $x,
        public readonly int $y,
    ) {
    }
}
