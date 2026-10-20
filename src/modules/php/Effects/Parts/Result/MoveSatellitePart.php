<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts\Result;

use Bga\Games\ProjectEdgeworth\Board\BoardLayout;
use Bga\Games\ProjectEdgeworth\Board\Movement;
use Bga\Games\ProjectEdgeworth\Board\SatellitePosition;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameEvent;
use Bga\Games\ProjectEdgeworth\Effects\Context\SatelliteState;
use Bga\Games\ProjectEdgeworth\Effects\LogEntry;
use Bga\Games\ProjectEdgeworth\Effects\Outcome;
use Bga\Games\ProjectEdgeworth\Effects\Parts\AbstractResultPart;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartCall;
use Bga\Games\ProjectEdgeworth\Effects\Question\Answer;
use Bga\Games\ProjectEdgeworth\Effects\Question\Field;
use Bga\Games\ProjectEdgeworth\Effects\Question\FieldType;
use Bga\Games\ProjectEdgeworth\Effects\Question\ValidationResult;
use Bga\Games\ProjectEdgeworth\Material\CostKind;
use Bga\Games\ProjectEdgeworth\Material\Hook;

/**
 * R07 `MOVE_SATELLITE`: 衛星を向きに沿って進め、燃料を消費する（P004・P034・P081・P082・P091）。移動は 003 §4.2（上下の端で反射、左右は接続）。
 * ニアミス判定・着陸判定は行わない（FAQ 行 4・34・59。ニアミスは O3、着陸は O4 の判定だけ）。
 *
 * パラメータ:
 * - `target`: `THIS`（この衛星）/ `CHOSEN_OWN_ACTIVE`（自分の稼働中の衛星から選ぶ。問い `satellite`）。
 * - `areas`（固定の区域数）。P034 は C2 の段階（`EffectCost::mayaTier`、支払い額 => 区域数）で決め、問い `tier` で支払い額を選ぶ。
 * - `fuelPerArea`: 1 区域につき消費する燃料（既定 1）。
 * - `fireEntered`: 真なら進んだ先の `ORBIT_ENTERED` を起こす（P081・P082・P091。007 未確定 9）。
 *
 * 燃料庫の外に出るところまでしか進めない（FAQ 行 15: 容量 8・消費 6 なら 3 区域まで）。燃料切れ（c > C）の衛星は動かせない
 * （001 §4-4: 庫外では燃料を消費する効果で動かせない）。庫外に出ても、その場では着陸しない（FAQ 行 59）。
 * 支払いは C1（P004・P081）ならエンジンが払い、C2（P034）なら `apply()` が払う（売却の同梱は検証で資金に数える）。
 */
final class MoveSatellitePart extends AbstractResultPart
{
    public function __construct(private readonly BoardLayout $layout)
    {
    }

    public function isAvailable(PartCall $call): bool
    {
        return $this->movable($call) !== [];
    }

    public function buildFields(PartCall $call): array
    {
        $fields = [];
        if (!$this->isThis($call)) {
            $fields[] = new Field('satellite', FieldType::Satellite, array_map(static fn(SatelliteState $s): int => $s->id, $this->movable($call)));
        }
        if ($this->tiers($call) !== []) {
            $max = $this->maxAreas($call, $this->movable($call)[0] ?? null);
            $affordable = array_keys(array_filter($this->tiers($call), static fn(int $areas): bool => $areas <= $max));
            sort($affordable);
            $fields[] = new Field('tier', FieldType::Amount, $affordable);
        }

        return $fields;
    }

    public function validate(PartCall $call, Answer $answer): ValidationResult
    {
        if ($this->tiers($call) === []) {
            return ValidationResult::ok();
        }
        $pay = (int) ($answer->fields['tier'] ?? 0);
        $view = $call->ctx->view;
        $playerId = $call->host->playerId;
        if ($view->maya($playerId) + count($answer->sell) * $view->sellPrice($playerId) < $pay) {
            return ValidationResult::error('not enough maya');
        }

        return ValidationResult::ok();
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $view = $call->ctx->view;
        $mutator = $call->ctx->mutator;
        $params = $call->spec->params;
        $satelliteId = $this->isThis($call) ? (int) $call->host->satelliteId : (int) ($answer?->fields['satellite'] ?? 0);
        $sat = $view->satelliteState($satelliteId);
        if ($sat === null || !$sat->isActive() || $sat->direction === null) {
            return Outcome::none();
        }
        $perArea = max(1, (int) ($params['fuelPerArea'] ?? 1));
        $playerId = $call->host->playerId;

        $areas = (int) ($params['areas'] ?? 1);
        if ($this->tiers($call) !== []) {
            $pay = (int) ($answer?->fields['tier'] ?? 0);
            $areas = $this->tiers($call)[$pay] ?? 0;
            if ($areas > 0) {
                $mutator->payMaya($playerId, $pay);
            }
        }
        $areas = min($areas, $this->maxAreas($call, $sat));
        if ($areas <= 0) {
            return Outcome::none();
        }

        $geometry = $this->layout->geometry();
        $from = $geometry->cellFromId((int) $sat->cell);
        $pos = new SatellitePosition($from->x, $from->y, $sat->direction);
        $fromRegion = $this->layout->region((int) $sat->cell)->value;
        for ($i = 0; $i < $areas; $i++) {
            $pos = Movement::move($geometry, $pos);
        }
        $cell = $geometry->cellId($pos->x, $pos->y);
        $mutator->moveSatellite($satelliteId, $cell, $pos->dir);
        $mutator->spendFuel($satelliteId, $areas * $perArea);

        $events = [];
        if (!empty($params['fireEntered'])) {
            $events[] = new GameEvent(
                Hook::OrbitEntered,
                $playerId,
                $satelliteId,
                cell: $cell,
                data: ['fromRegion' => $fromRegion, 'toRegion' => $this->layout->region($cell)->value, 'extra' => true],
            );
        }

        return new Outcome($events, [new LogEntry('satelliteMoved', [
            'player_id' => $playerId,
            'card_key' => $call->spec->cardKey,
            'sat_id' => $satelliteId,
            'from_cell' => (int) $sat->cell,
            'cell' => $cell,
            'areas' => $areas,
            'fuel' => $areas * $perArea,
        ])]);
    }

    public function preview(PartCall $call): array
    {
        if ($this->tiers($call) !== []) {
            return ['tiers' => $this->tiers($call)];
        }

        return ['areas' => (int) ($call->spec->params['areas'] ?? 1), 'fuel' => (int) ($call->spec->params['areas'] ?? 1) * (int) ($call->spec->params['fuelPerArea'] ?? 1)];
    }

    private function isThis(PartCall $call): bool
    {
        return ($call->spec->params['target'] ?? 'THIS') === 'THIS';
    }

    /**
     * 段階の支払い額 => 区域数（C2。なければ空）。
     *
     * @return array<int, int>
     */
    private function tiers(PartCall $call): array
    {
        return $call->spec->cost?->kind === CostKind::PayMayaTier ? $call->spec->cost->tiers : [];
    }

    /** 燃料の都合で進める区域数の上限（庫外に出るところまで）。1 区域も進められなければ 0 */
    private function maxAreas(PartCall $call, ?SatelliteState $sat): int
    {
        if ($sat === null) {
            return 0;
        }

        return intdiv($sat->stepsUntilOut(), max(1, (int) ($call->spec->params['fuelPerArea'] ?? 1)));
    }

    /**
     * 動かせる衛星（稼働中で、燃料庫の外に出ておらず、1 区域以上進める）。
     *
     * @return list<SatelliteState>
     */
    private function movable(PartCall $call): array
    {
        $view = $call->ctx->view;
        $ids = $this->isThis($call)
            ? ($call->host->satelliteId === null ? [] : [$call->host->satelliteId])
            : $view->satelliteIds($call->host->playerId);
        $result = [];
        foreach ($ids as $id) {
            $sat = $view->satelliteState($id);
            if ($sat !== null && $sat->isActive() && $sat->direction !== null && $this->maxAreas($call, $sat) >= 1) {
                $result[] = $sat;
            }
        }

        return $result;
    }
}
