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
 * K03 `FULL_TYPES`: 該当の衛星（イベントまたは問い合わせの衛星。なければ効果を持つカードのある衛星）に CREW・DEVICE・SOFTWARE が
 * すべて配備されていること。
 */
final class FullTypesCondition implements ConditionHandler
{
    public function __construct(private readonly GameView $view)
    {
    }

    public function holds(EffectCondition $condition, ConditionContext $context): bool
    {
        $satelliteId = $context->satelliteId() ?? $context->host->satelliteId;

        return $satelliteId !== null && SatelliteTypes::isFull($this->view, $context->host->playerId, $satelliteId);
    }
}
