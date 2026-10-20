<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Condition;

use Bga\Games\projectedgeworth\Material\EffectCondition;

/**
 * K08 `REGION_CHANGED`: 移動で地域が変わったこと（別の地域から突入した）。`params['regions']` があればその地域への突入に限る。
 * イベントの `data['fromRegion']`・`data['toRegion']`（地域の文字 A〜H）で判定する。どちらかが無ければ成立しない。
 */
final class RegionChangedCondition implements ConditionHandler
{
    public function holds(EffectCondition $condition, ConditionContext $context): bool
    {
        $data = $context->event->data ?? [];
        $from = $data['fromRegion'] ?? null;
        $to = $data['toRegion'] ?? null;
        if ($from === null || $to === null || $from === $to) {
            return false;
        }
        $regions = (array) ($condition->params['regions'] ?? []);

        return $regions === [] || in_array($to, $regions, true);
    }
}
