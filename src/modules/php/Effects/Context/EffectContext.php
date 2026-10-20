<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Context;

/**
 * 部品に渡すゲーム状態への接点。
 */
final readonly class EffectContext
{
    public function __construct(
        public GameView $view,
        public GameMutator $mutator,
    ) {
    }
}
