<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support;

use Bga\Games\projectedgeworth\Board\RandomSource;

/**
 * 種を指定できる乱数（線形合同法）。bga_rand の代わりに渡す。結果は種ごとに再現できる。
 */
final class SeededRandom implements RandomSource
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = ($seed * 2654435761 + 12345) & 0x7fffffff;
    }

    public function rand(int $min, int $max): int
    {
        $this->state = ($this->state * 1103515245 + 12345) & 0x7fffffff;
        return $min + ($this->state >> 8) % ($max - $min + 1);
    }
}
