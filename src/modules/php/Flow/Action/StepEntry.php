<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * 同時選択のステップに入った結果（009 §5.2）。
 */
final class StepEntry
{
    /**
     * @param list<int> $participants 参加者（席順。step_participants に保存済み）
     * @param list<int> $autoConfirmed 実行できる行動がなく、入場時に [Pass] 相当を確定した参加者
     * @param list<int> $active 選択が必要な参加者（setPlayersMultiactive に渡す）
     */
    public function __construct(
        public readonly array $participants,
        public readonly array $autoConfirmed,
        public readonly array $active,
    ) {
    }
}
