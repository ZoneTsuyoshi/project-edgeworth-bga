<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow;

/** 着陸 / 着水した衛星 1 機（001 §9-4）。 */
final class Landing
{
    public function __construct(
        public readonly int $satId,
        public readonly int $playerId,
        public readonly int $cell,
        public readonly bool $sea,
        public readonly int $maya,
    ) {
    }
}
