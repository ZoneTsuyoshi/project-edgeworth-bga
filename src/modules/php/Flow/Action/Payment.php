<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * 実際に行った支払い。売却は支払いの前に行う（008 §2-6）。
 */
final class Payment
{
    /**
     * @param list<int> $soldCards 売却したプロジェクトカード ID（売った順）
     */
    public function __construct(
        public readonly int $playerId,
        public readonly int $cost,
        public readonly array $soldCards,
        public readonly int $gainedFromSales,
    ) {
    }
}
