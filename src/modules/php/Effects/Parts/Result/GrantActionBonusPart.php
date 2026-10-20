<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Result;

use Bga\Games\projectedgeworth\Effects\LogEntry;
use Bga\Games\projectedgeworth\Effects\Outcome;
use Bga\Games\projectedgeworth\Effects\Parts\AbstractResultPart;
use Bga\Games\projectedgeworth\Effects\Parts\PartCall;
use Bga\Games\projectedgeworth\Effects\Question\Answer;

/**
 * R14 `GRANT_ACTION_BONUS`（R016〜R020 EUROPA）: 自分でそのアクションを選んでいなくても、そのアクション選択ボーナスを得る権利を宣言する。
 * 支払い C1（3 マヤ）はエンジンが [Use] のときに払う。`BONUS_DECLARE` のイベントの `data`: `action`（このフェイズのアクション）、
 * `chosenBy`（そのアクションを選んだプレイヤー。K13 が空でないことを見る。FAQ 行 62）、`holders`（すでにボーナスを得ているプレイヤー）。
 * すでに自分で選んで得ている・すでに宣言した場合は使えない（得るボーナスがない）。
 * 宣言で得たボーナスにも DEIMOS（R012〜R015）と P007 は働くが、R011 だけは自分で選んだときに限る（`GameView::declaredBonus()` が K12 に結ぶ。FAQ 行 71）。
 */
final class GrantActionBonusPart extends AbstractResultPart
{
    public function isAvailable(PartCall $call): bool
    {
        $playerId = $call->host->playerId;
        $holders = array_map('intval', (array) ($call->item->event->data['holders'] ?? []));

        return !in_array($playerId, $holders, true)
            && !$call->ctx->view->declaredBonus($playerId, (string) $call->spec->params['action']);
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $action = (string) $call->spec->params['action'];
        $call->ctx->mutator->declareBonus($call->host->playerId, $action);

        return new Outcome([], [new LogEntry('bonusDeclared', ['player_id' => $call->host->playerId, 'card_key' => $call->spec->cardKey, 'action' => $action])]);
    }

    public function preview(PartCall $call): array
    {
        return ['action' => (string) $call->spec->params['action']];
    }
}
