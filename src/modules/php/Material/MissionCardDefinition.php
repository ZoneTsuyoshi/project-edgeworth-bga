<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * ミッションカード 1 枚の定義（002 §8.2）。マスは 002 §8.1 のアキシャル座標 (q, r)（pointy-top、原点は最上行の最左マス）。
 */
final readonly class MissionCardDefinition
{
    /**
     * @param list<array{int, int}> $easyCells 易しい段階のマス (q, r)
     * @param list<array{int, int}> $extraCells 追加段階のマス (q, r)
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $nameId,
        public int $fameEasy,
        public int $fameFull,
        public array $easyCells,
        public array $extraCells,
    ) {
    }
}
