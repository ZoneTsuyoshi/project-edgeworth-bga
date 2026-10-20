<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Condition;

use Bga\Games\ProjectEdgeworth\Material\EffectCondition;

/**
 * K09 `LANDED_AREA_KIND`: 着陸 / 着水した区域の種類が `params['kind']`（`LAND` / `SEA`）であること。イベントの `data['areaKind']` で判定する。
 */
final class LandedAreaKindCondition implements ConditionHandler
{
    public function holds(EffectCondition $condition, ConditionContext $context): bool
    {
        $kind = $context->event->data['areaKind'] ?? null;

        return $kind !== null && $kind === (string) ($condition->params['kind'] ?? '');
    }
}
