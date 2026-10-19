<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Condition;

use Bga\Games\ProjectEdgeworth\Material\EffectCondition;

/**
 * K01 `EVENT_SATELLITE`: イベントの衛星が、このカードのある衛星 / 自分の他の衛星 / 自分のどれかであること。
 * `params['scope']` は `THIS` / `OTHER_OWN` / `ANY_OWN`。
 */
final class EventSatelliteCondition implements ConditionHandler
{
    public function holds(EffectCondition $condition, ConditionContext $context): bool
    {
        $satelliteId = $context->satelliteId();
        if ($satelliteId === null) {
            return false;
        }
        $own = $context->host->satelliteId;

        return match ((string) ($condition->params['scope'] ?? '')) {
            'THIS' => $satelliteId === $own,
            'OTHER_OWN' => $satelliteId !== $own,
            'ANY_OWN' => true,
            default => throw new \LogicException('EVENT_SATELLITE needs scope THIS / OTHER_OWN / ANY_OWN'),
        };
    }
}
