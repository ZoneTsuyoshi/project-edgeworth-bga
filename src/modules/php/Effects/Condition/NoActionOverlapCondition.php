<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Condition;

use Bga\Games\ProjectEdgeworth\Material\EffectCondition;

/**
 * K10 `NO_ACTION_OVERLAP`: 公開されたアクション選択が、他のどのプレイヤーとも被らないこと（P003）。
 * `PLANNING_REVEAL` のイベントの `data['choices']`（プレイヤー ID => アクション番号 1〜5）で判定する。全員の判定は同じ公開結果で行う。
 * 自分の選択が公開結果に無ければ成立しない。
 */
final class NoActionOverlapCondition implements ConditionHandler
{
    public function holds(EffectCondition $condition, ConditionContext $context): bool
    {
        $choices = $context->event->data['choices'] ?? [];
        $mine = $choices[$context->host->playerId] ?? null;
        if ($mine === null) {
            return false;
        }
        foreach ($choices as $playerId => $action) {
            if ((int) $playerId !== $context->host->playerId && (int) $action === (int) $mine) {
                return false;
            }
        }

        return true;
    }
}
