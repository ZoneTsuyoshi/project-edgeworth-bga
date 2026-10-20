<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Condition;

use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Effects\Context\GameView;
use Bga\Games\projectedgeworth\Material\ConditionId;

/**
 * E-1 の条件（K03・K06・K12）を `ConditionEvaluator` に登録する（011 §8）。K01・K02 は `ConditionEvaluator` が最初から持つ。
 */
final class ConditionsE1
{
    private function __construct()
    {
    }

    public static function register(ConditionEvaluator $evaluator, GameView $view, BoardLayout $layout): ConditionEvaluator
    {
        $evaluator->register(ConditionId::FullTypes, new FullTypesCondition($view));
        $evaluator->register(ConditionId::BelowRegion, new BelowRegionCondition($layout));
        $evaluator->register(ConditionId::OwnActionChoice, new OwnActionChoiceCondition());

        return $evaluator;
    }
}
