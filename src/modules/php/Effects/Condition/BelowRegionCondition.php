<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Condition;

use Bga\Games\ProjectEdgeworth\Board\BoardLayout;
use Bga\Games\ProjectEdgeworth\Material\EffectCondition;

/**
 * K06 `BELOW_REGION`: 衛星の直下の区域が `params['regions']`（地域の識別子 A〜H の配列）のどれかにあること。評価時点の位置で判定する
 * （P079・P080 は燃料消費の時点＝移動後。FAQ 行 33。P062・P063 は対象区域を決める時点の直下。007 未確定 13）。
 */
final class BelowRegionCondition implements ConditionHandler
{
    public function __construct(private readonly BoardLayout $layout)
    {
    }

    public function holds(EffectCondition $condition, ConditionContext $context): bool
    {
        $cell = $context->query->cell ?? $context->event->cell ?? null;
        if ($cell === null) {
            return false;
        }
        $regions = array_map('strval', (array) ($condition->params['regions'] ?? []));

        return in_array($this->layout->region($cell)->value, $regions, true);
    }
}
