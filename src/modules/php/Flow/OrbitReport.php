<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow;

/** 循環フェイズ 1 回の結果（通知用）。 */
final class OrbitReport
{
    /**
     * @param list<int> $nearMissIds ニアミスした衛星
     * @param list<Landing> $landings 着陸 / 着水した衛星
     */
    public function __construct(
        public readonly array $nearMissIds,
        public readonly array $landings,
    ) {
    }
}
