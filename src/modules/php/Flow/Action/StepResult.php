<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * 全員分の選択を一斉に反映した結果。outcomes は席順。
 */
final class StepResult
{
    /**
     * @param list<StepOutcome> $outcomes 席順
     * @param array<string, mixed> $extra ステップ全体の結果（通信確立のコミット番号など）
     */
    public function __construct(
        public readonly array $outcomes,
        public readonly array $extra = [],
    ) {
    }

    public function outcomeOf(int $playerId): ?StepOutcome
    {
        foreach ($this->outcomes as $outcome) {
            if ($outcome->playerId === $playerId) {
                return $outcome;
            }
        }
        return null;
    }

    /**
     * 1 人分の結果すべて（配備は 1 人が最大 3 件: 通常の配備、追加の配備、ドロー）。
     *
     * @return list<StepOutcome>
     */
    public function outcomesOf(int $playerId): array
    {
        return array_values(array_filter($this->outcomes, static fn(StepOutcome $o): bool => $o->playerId === $playerId));
    }
}
