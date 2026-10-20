<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Condition;

use Bga\Games\ProjectEdgeworth\Effects\Context\GameView;
use Bga\Games\ProjectEdgeworth\Material\EffectCondition;

/**
 * K07 `ENTERED_OWN_LINKED_AREA`: 突入した区域（イベントの `cell`）が、自分が通信確立済み（発射拠点の区域を含む。FAQ 行 44）であること。
 */
final class EnteredOwnLinkedAreaCondition implements ConditionHandler
{
    public function __construct(private readonly GameView $view)
    {
    }

    public function holds(EffectCondition $condition, ConditionContext $context): bool
    {
        $cell = $context->event->cell ?? null;

        return $cell !== null && $this->view->isOwnLinkedArea($context->host->playerId, $cell);
    }
}
