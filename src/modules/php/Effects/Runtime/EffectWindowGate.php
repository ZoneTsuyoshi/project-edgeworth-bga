<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Runtime;

use Bga\Games\projectedgeworth\Flow\Machine\WindowGate;

/**
 * `WindowGate` の本番実装（009 §6.1）: 窓のイベントにトリガーされる効果が 1 件でもあれば窓を開く（30 へ）。
 * なければ、その窓のイベントを捨てて素通りする。
 */
final class EffectWindowGate implements WindowGate
{
    public function __construct(private readonly EffectRuntime $runtime)
    {
    }

    public function needsWindow(string $windowId): bool
    {
        $events = $this->runtime->events->peek($windowId);
        if ($this->runtime->engine()->hasTriggers($windowId, $events)) {
            return true;
        }
        $this->runtime->events->discard($windowId);

        return false;
    }
}
