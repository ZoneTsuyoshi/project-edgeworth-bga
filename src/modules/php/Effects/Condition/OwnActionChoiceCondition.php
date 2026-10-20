<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Condition;

use Bga\Games\projectedgeworth\Material\EffectCondition;

/**
 * K12 `OWN_ACTION_CHOICE`: そのアクション選択ボーナスを、自分でそのアクションを選んで得ていること（R011。FAQ 行 71）。
 * R016〜R020 の宣言で得たボーナスでは成立しない。`QueryContext::$ownActionChoice` が null のときは「自分で選んだ」として扱う。
 */
final class OwnActionChoiceCondition implements ConditionHandler
{
    public function holds(EffectCondition $condition, ConditionContext $context): bool
    {
        return ($context->query->ownActionChoice ?? null) !== false;
    }
}
