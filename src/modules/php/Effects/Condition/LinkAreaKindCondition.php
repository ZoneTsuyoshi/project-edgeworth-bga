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
 * K05 `LINK_AREA_KIND`: 通信確立する区域が陸（`LAND`）か海（`SEA`）か。森林・山岳・雪原は陸、深海・岩礁は海（総称。`AreaType`）。
 */
final class LinkAreaKindCondition implements ConditionHandler
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
        $type = $this->layout->areaType($cell);

        return match ((string) ($condition->params['kind'] ?? '')) {
            'LAND' => $type->isLand(),
            'SEA' => $type->isSea(),
            default => throw new \LogicException('LINK_AREA_KIND needs kind LAND / SEA'),
        };
    }
}
