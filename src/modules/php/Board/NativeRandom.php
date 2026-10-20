<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/** PHP 標準の乱数（BGA 以外での利用・既定値用）。 */
final class NativeRandom implements RandomSource
{
    public function rand(int $min, int $max): int
    {
        return random_int($min, $max);
    }
}
