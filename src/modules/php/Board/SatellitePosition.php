<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Board;

/** 衛星コマの位置と向き（003 §4.3）。 */
final class SatellitePosition
{
    public function __construct(
        public readonly int $x,
        public readonly int $y,
        public readonly Direction $dir,
    ) {
    }
}
