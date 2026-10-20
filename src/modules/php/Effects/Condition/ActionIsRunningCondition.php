<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Condition;

use Bga\Games\projectedgeworth\Material\EffectCondition;

/**
 * K13 `ACTION_IS_RUNNING`: そのアクションが誰かに選ばれ、実施されるラウンドであること（誰も選んでいなければ EUROPA は使えない。FAQ 行 62）。
 * `BONUS_DECLARE` のイベントの `data['action']`（いま宣言を聞いているフェイズのアクション）が `params['action']` と一致し、
 * `data['chosenBy']`（そのアクションを選んだプレイヤー）が空でないこと。
 */
final class ActionIsRunningCondition implements ConditionHandler
{
    public function holds(EffectCondition $condition, ConditionContext $context): bool
    {
        $data = $context->event->data ?? [];

        return ($data['action'] ?? null) === (string) ($condition->params['action'] ?? '')
            && (array) ($data['chosenBy'] ?? []) !== [];
    }
}
