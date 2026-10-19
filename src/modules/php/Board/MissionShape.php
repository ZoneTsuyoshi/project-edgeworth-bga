<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Board;

/**
 * ミッション 1 枚の形状（003 §8.1）。セルはダブルド座標 (row, x) の相対位置。
 */
final class MissionShape
{
    /**
     * @param list<array{0: int, 1: int}> $solid [row, x]
     * @param list<array{0: int, 1: int}> $hatched [row, x]
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $solid,
        public readonly array $hatched,
        public readonly int $fameEasy,
        public readonly int $fameExtra,
    ) {
    }
}
