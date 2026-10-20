<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Board;

use Bga\Games\projectedgeworth\Board\RandomSource;

/** テスト用の固定列の乱数。値は範囲に収めて循環して返す。 */
final class FixedRandom implements RandomSource
{
    private int $i = 0;

    /** @param list<int> $values */
    public function __construct(private readonly array $values)
    {
    }

    public function rand(int $min, int $max): int
    {
        $v = $this->values[$this->i++ % count($this->values)];
        return $min + ($v % ($max - $min + 1));
    }
}
