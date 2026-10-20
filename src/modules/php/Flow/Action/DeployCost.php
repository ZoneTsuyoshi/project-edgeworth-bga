<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * 配備コストの内訳（007 §5-2）。total = max(0, base + activeSurcharge − reduction)。下限 0 は最後に 1 回だけ。
 */
final class DeployCost
{
    public function __construct(
        public readonly int $base,
        public readonly int $activeSurcharge,
        public readonly int $reduction,
    ) {
    }

    public function total(): int
    {
        return max(0, $this->base + $this->activeSurcharge - $this->reduction);
    }
}
