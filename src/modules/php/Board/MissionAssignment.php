<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/** 2 枚（以下）のミッションの最適な割り当て結果。 */
final class MissionAssignment
{
    /**
     * @param array<int, MissionCandidate|null> $results ミッション番号 => 採用した候補（達成しないなら null）
     */
    public function __construct(
        public readonly int $totalFame,
        public readonly array $results,
    ) {
    }
}
