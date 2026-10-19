<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

/**
 * 企画（V）で引く枚数と残す枚数（007 §5-5）。
 */
final class ProposalCounts
{
    public function __construct(
        public readonly int $draw,
        public readonly int $keep,
    ) {
    }
}
