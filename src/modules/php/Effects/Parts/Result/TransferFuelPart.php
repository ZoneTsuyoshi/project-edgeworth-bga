<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Result;

use Bga\Games\projectedgeworth\Effects\Context\SatelliteState;
use Bga\Games\projectedgeworth\Effects\LogEntry;
use Bga\Games\projectedgeworth\Effects\Outcome;
use Bga\Games\projectedgeworth\Effects\Parts\AbstractResultPart;
use Bga\Games\projectedgeworth\Effects\Parts\PartCall;
use Bga\Games\projectedgeworth\Effects\Question\Answer;
use Bga\Games\projectedgeworth\Effects\Question\Field;
use Bga\Games\projectedgeworth\Effects\Question\FieldType;

/**
 * R06 `TRANSFER_FUEL`（P038）: 打ち上げた衛星（イベントの衛星）の燃料を、残り燃料の範囲で好きなだけ減らし、自分の他の稼働中の衛星 1 機の燃料を
 * 減らした分だけ回復する。減らせるのは残り燃料 R = C − c まで（庫外に出ない。FAQ 行 15・18）、回復は初期燃料庫まで（FAQ 行 19。余りは捨てる）。
 *
 * 問い: `amount`（0〜R。0 は何もしない）と `satellite`（回復先。実際に回復できる = 満タンでも燃料切れでもない他の稼働中の衛星）。
 * 回復先が 1 機もなければ使えない（満タン・庫外では発動した扱いにしない）。
 */
final class TransferFuelPart extends AbstractResultPart
{
    public function isAvailable(PartCall $call): bool
    {
        $source = $this->source($call);

        return $source !== null && $source->isActive() && $source->remainingFuel() >= 1 && $this->targets($call, $source) !== [];
    }

    public function buildFields(PartCall $call): array
    {
        $source = $this->source($call);
        if ($source === null) {
            return [];
        }

        return [
            new Field('amount', FieldType::Amount, range(0, $source->remainingFuel())),
            new Field('satellite', FieldType::Satellite, array_map(static fn(SatelliteState $s): int => $s->id, $this->targets($call, $source))),
        ];
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $source = $this->source($call);
        $amount = (int) ($answer?->fields['amount'] ?? 0);
        if ($source === null || $amount <= 0) {
            return Outcome::none();
        }
        $amount = min($amount, $source->remainingFuel());
        $target = (int) ($answer?->fields['satellite'] ?? 0);
        $call->ctx->mutator->spendFuel($source->id, $amount);
        $recovered = $call->ctx->mutator->recoverFuel($target, $amount);

        return new Outcome([], [new LogEntry('fuelTransferred', [
            'player_id' => $call->host->playerId,
            'card_key' => $call->spec->cardKey,
            'from_sat_id' => $source->id,
            'to_sat_id' => $target,
            'amount' => $amount,
            'recovered' => $recovered,
        ])]);
    }

    private function source(PartCall $call): ?SatelliteState
    {
        $id = $call->item->event->satelliteId;

        return $id === null ? null : $call->ctx->view->satelliteState($id);
    }

    /**
     * @return list<SatelliteState>
     */
    private function targets(PartCall $call, SatelliteState $source): array
    {
        $view = $call->ctx->view;
        $result = [];
        foreach ($view->satelliteIds($call->host->playerId) as $id) {
            $sat = $view->satelliteState($id);
            if ($sat !== null && $id !== $source->id && $sat->isActive() && $view->canRecoverFuel($id)) {
                $result[] = $sat;
            }
        }

        return $result;
    }
}
