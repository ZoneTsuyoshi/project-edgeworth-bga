<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

use Bga\Games\projectedgeworth\Data\Satellite;

/**
 * 転回できる稼働中の衛星 1 機の候補（008 §3-2）。
 */
final class TurnOption
{
    public const BLOCKED_FORBIDDEN = 'forbidden';
    public const BLOCKED_NO_INACTIVE = 'no_inactive_satellite';

    public function __construct(
        public readonly Satellite $satellite,
        public readonly ?string $blocked,
    ) {
    }

    public function isSelectable(): bool
    {
        return $this->blocked === null;
    }
}
