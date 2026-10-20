<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 褒賞カード 1 面の定義（002 §7.1）。`points` は 1 位から 4 位の名声。
 */
final readonly class RewardCardDefinition
{
    /**
     * @param list<int> $points 1 位〜4 位の名声
     */
    public function __construct(
        public string $id,
        public string $nameJa,
        public string $nameEn,
        public string $nameId,
        public string $compareJa,
        public string $compareEn,
        public RewardClass $class,
        public RewardMark $mark,
        public array $points,
    ) {
    }
}
