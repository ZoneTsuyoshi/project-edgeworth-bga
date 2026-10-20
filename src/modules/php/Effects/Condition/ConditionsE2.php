<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Condition;

use Bga\Games\projectedgeworth\Effects\Context\GameView;
use Bga\Games\projectedgeworth\Material\ConditionId;

/**
 * E-2 で使う条件 K07〜K11 の登録。K07 は盤面（通信確立済みの区域）を読むので `GameView` が要る。
 */
final class ConditionsE2
{
    private function __construct()
    {
    }

    public static function register(ConditionEvaluator $evaluator, GameView $view): ConditionEvaluator
    {
        $evaluator->register(ConditionId::EnteredOwnLinkedArea, new EnteredOwnLinkedAreaCondition($view));
        $evaluator->register(ConditionId::RegionChanged, new RegionChangedCondition());
        $evaluator->register(ConditionId::LandedAreaKind, new LandedAreaKindCondition());
        $evaluator->register(ConditionId::NoActionOverlap, new NoActionOverlapCondition());
        $evaluator->register(ConditionId::IsBonusHolder, new BonusHolderCondition());

        return $evaluator;
    }
}
