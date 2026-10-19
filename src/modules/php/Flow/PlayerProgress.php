<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow;

/** 終了判定・進行度の入力となる、1 人の状況。 */
final class PlayerProgress
{
    /**
     * @param int $activeSatellites 稼働中の衛星の数（着陸 / 着水判定の後。着陸猶予は含めない。FAQ 行 50）
     * @param int $links 通信確立数（キューブ。発射拠点・放棄したキューブは数えない）
     */
    public function __construct(
        public readonly int $playerId,
        public readonly int $activeSatellites,
        public readonly int $links,
    ) {
    }
}
