<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/** 乱数の注入口。BGA では bga_rand を包み、テストでは固定列を返す。 */
interface RandomSource
{
    /** @return int min 以上 max 以下の整数 */
    public function rand(int $min, int $max): int;
}
