<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Condition;

use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Material\EffectCondition;

/**
 * K04 `LINK_AREA_REGION`: 通信確立した（する）区域が `params['regions']`（A〜H）のどれかにあること。衛星のいる地域ではなく、
 * 区域の地域で判定する（FAQ 行 22・73）。通信確立コストの問い合わせでは `QueryContext::$cell`、`LINKED` のイベントでは `GameEvent::$cell`。
 */
final class LinkAreaRegionCondition implements ConditionHandler
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

        return in_array($this->layout->region($cell)->value, array_map('strval', (array) ($condition->params['regions'] ?? [])), true);
    }
}
