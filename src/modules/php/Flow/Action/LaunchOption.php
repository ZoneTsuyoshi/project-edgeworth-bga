<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

use Bga\Games\ProjectEdgeworth\Data\Satellite;

/**
 * 打ち上げる衛星 1 機の候補（008 §3-2）。blocked があれば選べない（グレーで理由を出す）。
 */
final class LaunchOption
{
    public const BLOCKED_ACTIVE = 'active';
    public const BLOCKED_NO_PROJECT = 'no_project';
    public const BLOCKED_NO_PAD = 'no_launch_pad';

    public function __construct(
        public readonly Satellite $satellite,
        public readonly int $deployedCount,
        public readonly int $cost,
        public readonly Payability $payability,
        public readonly ?string $blocked,
    ) {
    }

    public function isSelectable(): bool
    {
        return $this->blocked === null && $this->payability->canPayWithSales();
    }
}
