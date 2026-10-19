<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

/**
 * 1 回の通信確立の依頼（ステップのメイン / ボーナスの選択、または窓でステージされた効果による通信確立）。
 * コストは依頼を作る時点（処理前の盤面）で確定させる。
 */
final class LinkRequest
{
    /**
     * @param list<int> $sell 支払いの前に売却するカード ID
     */
    public function __construct(
        public readonly int $playerId,
        public readonly int $satelliteId,
        public readonly int $cell,
        public readonly bool $special,
        public readonly LinkCost $cost,
        public readonly array $sell = [],
    ) {
    }
}
