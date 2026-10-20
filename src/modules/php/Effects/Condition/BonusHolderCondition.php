<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Condition;

use Bga\Games\ProjectEdgeworth\Material\EffectCondition;

/**
 * K11 `IS_BONUS_HOLDER`: そのフェイズのアクション選択ボーナスを得ていること（自分で選んだ、または EUROPA の宣言で得た）。
 * `BUDGET_BONUS_GAIN` のイベントの `data['holders']`（list<プレイヤー ID>）に自分が含まれるかで判定する。
 */
final class BonusHolderCondition implements ConditionHandler
{
    public function holds(EffectCondition $condition, ConditionContext $context): bool
    {
        $holders = array_map('intval', (array) ($context->event->data['holders'] ?? []));

        return in_array($context->host->playerId, $holders, true);
    }
}
