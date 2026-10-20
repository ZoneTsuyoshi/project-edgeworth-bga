<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Board;

use Bga\Games\projectedgeworth\Board\RandomSource;

/** テスト用の再現可能な乱数（線形合同法）。 */
final class SeededRandom implements RandomSource
{
    public function __construct(private int $state)
    {
    }

    public function rand(int $min, int $max): int
    {
        $this->state = ($this->state * 1103515245 + 12345) & 0x7fffffff;
        return $min + ($this->state >> 8) % ($max - $min + 1);
    }
}
