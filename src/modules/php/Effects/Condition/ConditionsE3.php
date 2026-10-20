<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Condition;

use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Material\ConditionId;

/**
 * E-3 の条件 K04・K05・K13 の登録。K04・K05 は区域の地域・種類を読むので `BoardLayout` が要る。
 */
final class ConditionsE3
{
    private function __construct()
    {
    }

    public static function register(ConditionEvaluator $evaluator, BoardLayout $layout): ConditionEvaluator
    {
        $evaluator->register(ConditionId::LinkAreaRegion, new LinkAreaRegionCondition($layout));
        $evaluator->register(ConditionId::LinkAreaKind, new LinkAreaKindCondition($layout));
        $evaluator->register(ConditionId::ActionIsRunning, new ActionIsRunningCondition());

        return $evaluator;
    }
}
