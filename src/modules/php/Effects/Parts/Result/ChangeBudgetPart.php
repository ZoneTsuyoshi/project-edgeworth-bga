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
 * R02 `CHANGE_BUDGET`: 予算を上げる（上限なし）。`amount`、`perDevice`（この衛星の DEVICE の枚数 1 枚につき。P042。解決の時点で数える）。
 * `ACTION_BONUS_STEP` の効果（R012）は、定義の `action` のボーナスのときだけ働く。
 */
final class ChangeBudgetPart extends AbstractResultPart
{
    public function isAvailable(PartCall $call): bool
    {
        return ActionBonusFilter::allows($call);
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $params = $call->spec->params;
        $amount = (int) ($params['amount'] ?? 0);
        if (!empty($params['perDevice'])) {
            $amount *= $call->ctx->view->deviceCountOn((int) $call->host->satelliteId);
        }
        $call->ctx->mutator->changeBudget($call->host->playerId, $amount);

        return new Outcome([], [new LogEntry('budgetChanged', ['player_id' => $call->host->playerId, 'card_key' => $call->spec->cardKey, 'amount' => $amount])]);
    }
}
