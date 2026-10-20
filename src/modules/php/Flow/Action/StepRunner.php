<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

use Bga\Games\projectedgeworth\Data\ChoiceRepository;
use Bga\Games\projectedgeworth\Data\GlobalValues;

/**
 * 全員同時の選択の共通パターン（009 §5.2、008 §2-1）を、Table に依存せずに行う。状態クラス（I-07）はこれを呼ぶだけにする。
 *
 *   enter()   … 状態に入るとき。参加者を決め、前回の確定を消し、行動できない人を自動確定する
 *   confirm() … act* のとき。検証して上書き保存（全員確定まで何度でも）。全員確定したかを返す
 *   zombie()  … zombie のとき。既定の選択を確定する
 *   resolve() … 全員確定の後。一斉に反映し、確定一覧を消す
 *
 * 全員確定の判定は choice テーブルの行数が正（ChoiceRepository::allConfirmed）。
 */
final class StepRunner
{
    public function __construct(
        private readonly ChoiceRepository $choices,
        private readonly GlobalValues $globals,
    ) {
    }

    public function enter(ActionStep $step, int $stateId, StepMode $mode = StepMode::Main): StepEntry
    {
        $participants = $step->participants($mode);
        $this->globals->setStepParticipants($participants);
        $this->choices->clear($stateId);
        $auto = [];
        $active = [];
        foreach ($participants as $playerId) {
            if ($step->canAct($playerId, $mode)) {
                $active[] = $playerId;
                continue;
            }
            $this->choices->submit($playerId, $stateId, $step->defaultChoice($playerId, $mode), $participants, true);
            $auto[] = $playerId;
        }
        return new StepEntry($participants, $auto, $active);
    }

    /**
     * @param array<mixed> $data
     * @return bool 全員が確定したか
     * @throws RuleViolation
     */
    public function confirm(ActionStep $step, int $stateId, int $playerId, array $data, StepMode $mode = StepMode::Main): bool
    {
        $participants = $this->globals->stepParticipants();
        if (!in_array($playerId, $participants, true)) {
            throw new RuleViolation('You are not part of this step');
        }
        $step->validate($playerId, $mode, $data);
        $this->choices->submit($playerId, $stateId, $data, $participants);
        return $this->choices->allConfirmed($stateId, $participants);
    }

    public function zombie(ActionStep $step, int $stateId, int $playerId, StepMode $mode = StepMode::Main): bool
    {
        $participants = $this->globals->stepParticipants();
        $this->choices->submit($playerId, $stateId, $step->defaultChoice($playerId, $mode), $participants, true);
        return $this->choices->allConfirmed($stateId, $participants);
    }

    public function resolve(ActionStep $step, int $stateId, StepMode $mode = StepMode::Main): StepResult
    {
        $participants = $this->globals->stepParticipants();
        $confirmed = $this->choices->revealAll($stateId);
        $choices = [];
        foreach ($participants as $playerId) {
            $choices[$playerId] = $confirmed[$playerId] ?? $step->defaultChoice($playerId, $mode);
        }
        $result = $step->resolve($mode, $choices);
        $this->choices->clear($stateId);
        return $result;
    }
}
