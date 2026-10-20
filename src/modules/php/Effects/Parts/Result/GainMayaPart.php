<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts\Result;

use Bga\Games\ProjectEdgeworth\Effects\LogEntry;
use Bga\Games\ProjectEdgeworth\Effects\Outcome;
use Bga\Games\ProjectEdgeworth\Effects\Parts\AbstractResultPart;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartCall;
use Bga\Games\ProjectEdgeworth\Effects\Question\Answer;

/**
 * R01 `GAIN_MAYA`: マヤを得る。`amount`（固定）、`perCardDrawn`（P075: イベントの `data['count']`＝企画フェイズ中に引いた枚数 1 枚につき）。
 */
final class GainMayaPart extends AbstractResultPart
{
    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $params = $call->spec->params;
        $amount = isset($params['perCardDrawn'])
            ? (int) $params['perCardDrawn'] * (int) ($call->item->event->data['count'] ?? 0)
            : (int) ($params['amount'] ?? 0);
        $call->ctx->mutator->gainMaya($call->host->playerId, $amount);

        return new Outcome([], [new LogEntry('mayaGained', ['player_id' => $call->host->playerId, 'card_key' => $call->spec->cardKey, 'amount' => $amount])]);
    }
}
