<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

/**
 * 最終得点（121）の結果: 通知と、プレイヤーごとの名声の合計（player_score に書く）。
 */
final class FinalScoreOutcome
{
    /**
     * @param list<Notice> $notices
     * @param array<int, int> $scores プレイヤー ID => 名声の合計（席順）
     * @param array<int, array<string, mixed>> $details プレイヤー ID => 名声の内訳（fame_detail。統計用）
     * @param array<int, int> $cubesLeft プレイヤー ID => 残りのキューブ（統計用）
     */
    public function __construct(
        public readonly array $notices,
        public readonly array $scores,
        public readonly array $details = [],
        public readonly array $cubesLeft = [],
    ) {
    }
}
