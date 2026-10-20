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
 * R05 `RECOVER_FUEL`: 燃料を回復する。`amount`、`target`（`THIS` = このカードのある衛星 / `EVENT_SATELLITE` = イベントの衛星）。
 * 初期燃料庫より下には動かず、庫外のコマは動かさない（001 §4-4、FAQ 行 14・19。`GameMutator::recoverFuel()` が丸める）。
 * 満タン（消費量 0）・庫外の衛星は実際に回復しないので発動した扱いにしない（作者回答 2026-10-20）: `isAvailable()` が偽で、起動（P043・P083・P084）は
 * [Use] / [Skip] を聞かず使用済みにもならず、常時（P049）は何も起きない。
 */
final class RecoverFuelPart extends AbstractResultPart
{
    public function isAvailable(PartCall $call): bool
    {
        $satelliteId = $this->satelliteId($call);

        return $satelliteId !== null && $call->ctx->view->canRecoverFuel($satelliteId);
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
