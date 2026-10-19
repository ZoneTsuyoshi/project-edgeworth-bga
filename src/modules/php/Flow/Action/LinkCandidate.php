<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

/**
 * 通信確立可能区域 1 つ（001 §7-5 の (i)(ii)）と、それを通信確立できる衛星の候補。
 * (iii) 払えるかは各衛星の payability に入っている（売却すれば払える区域は isSelectable()）。
 */
final class LinkCandidate
{
    /**
     * @param list<LinkSatelliteOption> $options 衛星 ID の昇順
     */
    public function __construct(
        public readonly int $cell,
        public readonly array $options,
    ) {
    }

    /** 売却を使っても払えない区域は選べない（グレー） */
    public function isSelectable(): bool
    {
        foreach ($this->options as $option) {
            if ($option->payability->canPayWithSales()) {
                return true;
            }
        }
        return false;
    }
}
