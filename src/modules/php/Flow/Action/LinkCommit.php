<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

/**
 * 通信確立の一斉コミットの結果（007 §7-3 の世代 1 つ分）。
 */
final class LinkCommit
{
    /**
     * @param list<LinkCommitEntry> $entries 席順
     * @param list<LinkRequest> $failed コストを払えず取りやめた依頼
     * @param array<int, int> $pioneerReceived プレイヤー ID => 受け取った先駆コスト
     */
    public function __construct(
        public readonly ?int $commitNo,
        public readonly array $entries,
        public readonly array $failed,
        public readonly array $pioneerReceived,
    ) {
    }
}
