<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

use Bga\Games\ProjectEdgeworth\Data\Link;

/**
 * コミットされた通信確立 1 回分の結果。
 */
final class LinkCommitEntry
{
    public function __construct(
        public readonly LinkRequest $request,
        public readonly Payment $payment,
        public readonly Link $link,
        public readonly AreaBonus $bonus,
    ) {
    }
}
