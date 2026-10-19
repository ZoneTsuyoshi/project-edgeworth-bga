<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

use Bga\Games\ProjectEdgeworth\Data\Satellite;

/**
 * あるプロジェクトカードを配備する先の衛星 1 機と、その場合のコスト・選べない理由（008 §3-6）。
 */
final class DeployTarget
{
    public const BLOCKED_FULL = 'full';
    public const BLOCKED_FORBIDDEN = 'forbidden';

    public function __construct(
        public readonly Satellite $satellite,
        public readonly int $deployedCount,
        public readonly DeployCost $cost,
        public readonly Payability $payability,
        public readonly ?string $blocked,
    ) {
    }

    /** 配備先として選べるか（4 枚未満・禁止なし・売却を使えば払える） */
    public function isSelectable(): bool
    {
        return $this->blocked === null && $this->payability->canPayWithSales();
    }
}
