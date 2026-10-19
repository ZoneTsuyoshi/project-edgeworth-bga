<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Condition;

use Bga\Games\ProjectEdgeworth\Material\EffectCondition;

/**
 * K02 `EVENT_CARD_TYPE`: 配備されたカードのタイプが `params['type']`（CREW / DEVICE / SOFTWARE）であること。
 */
final class EventCardTypeCondition implements ConditionHandler
{
    public function holds(EffectCondition $condition, ConditionContext $context): bool
    {
        $type = $context->cardType();

        return $type !== null && $type->value === (string) ($condition->params['type'] ?? '');
    }
}
