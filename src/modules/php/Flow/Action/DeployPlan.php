<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * 1 人分の配備の選択（001 §7-3、作者回答 2026-10-20）: 通常の配備（任意）+ 追加の配備またはドロー（ボーナス保持者のみ）。
 * 支払い（売却の同梱を含む）は配備 1〜2 枚の合計コストに対して 1 回だけ行う。
 */
final class DeployPlan
{
    /**
     * @param list<DeployStep> $steps 配備する順（通常の配備、追加の配備）。0〜2 件
     * @param bool $draw 追加の行動として 1 枚引く
     * @param list<int> $sell 同梱する売却
     */
    public function __construct(
        public readonly array $steps,
        public readonly bool $draw,
        public readonly array $sell,
    ) {
    }

    public function totalCost(): int
    {
        return array_sum(array_map(static fn(DeployStep $s): int => $s->target->cost->total(), $this->steps));
    }

    /** @return list<int> 配備するカード（売れない） */
    public function cards(): array
    {
        return array_map(static fn(DeployStep $s): int => $s->card, $this->steps);
    }
}
