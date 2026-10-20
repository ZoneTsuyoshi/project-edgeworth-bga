<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

/**
 * 効果なしの窓（今回の結線）。どの窓もトリガーされた効果がないものとして、開かずに素通りする（009 §6.1 の最後の項）。
 */
final class NoEffectWindowGate implements WindowGate
{
    public function needsWindow(string $windowId): bool
    {
        return false;
    }
}
