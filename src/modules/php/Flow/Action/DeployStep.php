<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * 配備 1 枚分: カード、配備先（このカードのコストと段を含む）、使う起動の修正。
 */
final class DeployStep
{
    /**
     * @param list<string> $mods このカードのコストに使う起動の修正の宿主の鍵
     */
    public function __construct(
        public readonly int $card,
        public readonly DeployTarget $target,
        public readonly array $mods,
    ) {
    }
}
