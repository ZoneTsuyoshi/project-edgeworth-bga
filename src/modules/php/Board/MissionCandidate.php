<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Board;

/** 1 枚のミッションの達成候補（配置 × 段階）。 */
final class MissionCandidate
{
    /**
     * @param list<int> $cells 使う区域 ID（昇順）
     */
    public function __construct(
        public readonly int $missionId,
        public readonly MissionTier $tier,
        public readonly int $fame,
        public readonly array $cells,
    ) {
    }
}
