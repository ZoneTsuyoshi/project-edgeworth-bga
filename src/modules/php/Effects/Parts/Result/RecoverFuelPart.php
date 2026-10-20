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
 * R05 `RECOVER_FUEL`: 燃料を回復する。`amount`、`target`（`THIS` = このカードのある衛星 / `EVENT_SATELLITE` = イベントの衛星）。
 * 初期燃料庫より下には動かず、庫外のコマは動かさない（001 §4-4、FAQ 行 14・19。`GameMutator::recoverFuel()` が丸める）。
 */
final class RecoverFuelPart extends AbstractResultPart
{
    public function isAvailable(PartCall $call): bool
    {
        return $this->satelliteId($call) !== null;
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $satelliteId = (int) $this->satelliteId($call);
        $recovered = $call->ctx->mutator->recoverFuel($satelliteId, (int) ($call->spec->params['amount'] ?? 0));

        return new Outcome([], [new LogEntry('fuelRecovered', [
            'player_id' => $call->host->playerId,
            'card_key' => $call->spec->cardKey,
            'sat_id' => $satelliteId,
            'amount' => $recovered,
        ])]);
    }

    private function satelliteId(PartCall $call): ?int
    {
        return ($call->spec->params['target'] ?? 'THIS') === 'EVENT_SATELLITE'
            ? $call->item->event->satelliteId
            : $call->host->satelliteId;
    }
}
