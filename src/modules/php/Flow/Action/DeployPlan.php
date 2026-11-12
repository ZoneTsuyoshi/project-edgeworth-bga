<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * 配備の 1 回分の選択（001 §7-3、009 追補 2026-11-12）。未ステージ（S0）は通常の配備（任意）+ ドロー（ボーナス保持者のみ）、
 * ステージ済み（S1）は 2 枚目（任意）。配備は 1 回に 0〜1 枚で、支払い（売却の同梱を含む）はその 1 枚のコストに対して行う。
 */
final class DeployPlan
{
    /**
     * @param list<DeployStep> $steps 配備するカード。0〜1 件
     * @param bool $draw ボーナスの行動として 1 枚引く（S0 のみ）
     * @param list<int> $sell 配備するカードのコストに同梱する売却
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
