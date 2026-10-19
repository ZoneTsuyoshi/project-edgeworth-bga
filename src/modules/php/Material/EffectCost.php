<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * 効果を使う代価（011 §2-5）。`amount` は C1 の固定額、`tiers` は C2 の段階（支払い額 => 結果の量）。C3 の枚数は `amount`。
 */
final readonly class EffectCost
{
    /**
     * @param array<int, int> $tiers 段階ごとの 支払い額 => 結果の量（C2 のみ）
     */
    public function __construct(
        public CostKind $kind,
        public int $amount = 0,
        public array $tiers = [],
    ) {
    }

    public static function maya(int $amount): self
    {
        return new self(CostKind::PayMaya, $amount);
    }

    /**
     * @param array<int, int> $tiers
     */
    public static function mayaTier(array $tiers): self
    {
        return new self(CostKind::PayMayaTier, 0, $tiers);
    }

    public static function discard(int $count): self
    {
        return new self(CostKind::DiscardCost, $count);
    }
}
