<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Condition;

use Bga\Games\ProjectEdgeworth\Material\EffectCondition;

/**
 * 条件 1 種の判定。E-1 以降で K03〜K13 のハンドラを足し、`ConditionEvaluator` に登録する。
 */
interface ConditionHandler
{
    public function holds(EffectCondition $condition, ConditionContext $context): bool;
}
