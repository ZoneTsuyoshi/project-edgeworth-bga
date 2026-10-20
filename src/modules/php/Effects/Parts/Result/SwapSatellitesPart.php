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
 * R13 `SWAP_SATELLITES`（P024）: 自分の稼働中の衛星 1 機を選び、その衛星とこのカードのある衛星の稼働状態・燃料消費量・場所を入れ替える。
 * 打ち上げ・着陸のイベントは起こさない。入れ替えで燃料庫の数を超えても、その場では着陸しない（循環フェイズの着陸 / 着水判定で着陸。FAQ 行 12〜14）。
 *
 * 消費量 c は数値のまま移すが、燃料庫の外に出ている衛星（燃料切れ）は燃料切れのまま移る（FAQ 行 13: 庫外のコマは配備でも回復でも戻らない）。
 * 移った先の燃料庫の数 C を超える c は C + 1（燃料切れ）に丸める。
 */
final class SwapSatellitesPart extends AbstractResultPart
{
    public function isAvailable(PartCall $call): bool
    {
        return $this->candidates($call) !== [];
    }

    public function buildFields(PartCall $call): array
    {
        return [new Field('satellite', FieldType::Satellite, array_map(static fn(SatelliteState $s): int => $s->id, $this->candidates($call)))];
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $view = $call->ctx->view;
        $mutator = $call->ctx->mutator;
        $a = $call->host->satelliteId === null ? null : $view->satelliteState($call->host->satelliteId);
        $b = $view->satelliteState((int) ($answer?->fields['satellite'] ?? 0));
        if ($a === null || $b === null || !$b->isActive()) {
            return Outcome::none();
        }
        $mutator->swapSatellites($a->id, $b->id);
        // a は b の、b は a の状態を受け取る。稼働中になった方だけ消費量を移った先の燃料庫に合わせる
        $mutator->setFuelUsed($a->id, self::carried($b, $a));
        if ($a->isActive()) {
            $mutator->setFuelUsed($b->id, self::carried($a, $b));
        }

        return new Outcome([], [new LogEntry('satellitesSwapped', [
            'player_id' => $call->host->playerId,
            'card_key' => $call->spec->cardKey,
            'sat_id' => $a->id,
            'other_sat_id' => $b->id,
        ])]);
    }

    /** `$from` の燃料の状態を `$into` の燃料庫に移したときの消費量 */
    private static function carried(SatelliteState $from, SatelliteState $into): int
    {
        return $from->isOutOfFuel() ? $into->capacity + 1 : min($from->fuelUsed, $into->capacity + 1);
    }

    /**
     * @return list<SatelliteState> このカードのある衛星以外の、自分の稼働中の衛星
     */
    private function candidates(PartCall $call): array
    {
        $view = $call->ctx->view;
        $result = [];
        foreach ($view->satelliteIds($call->host->playerId) as $id) {
            $sat = $view->satelliteState($id);
            if ($sat !== null && $id !== $call->host->satelliteId && $sat->isActive()) {
                $result[] = $sat;
            }
        }

        return $result;
    }
}
