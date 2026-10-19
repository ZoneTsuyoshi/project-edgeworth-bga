<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * 効果の条件 1 件（011 §2-6）。`params` の形は条件ごと（例: K01 は `['scope' => 'THIS' | 'OTHER_OWN' | 'ANY_OWN']`、
 * K02 は `['type' => 'CREW' | 'DEVICE' | 'SOFTWARE']`）。
 */
final readonly class EffectCondition
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public ConditionId $id,
        public array $params = [],
    ) {
    }
}
