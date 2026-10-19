<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

use Bga\Games\ProjectEdgeworth\Data\ActionCardRepository;

/**
 * 計画フェイズ（001 §6-1、008 §3-1、009 の 21〜23）。
 *
 * 1. collect(): アクションカードの回収（横 → 手札、縦の I → 手札、残りの縦 → 横）。操作は不要。
 * 2. 全員が手札のアクションカードを 1 枚選ぶ（ActionStep の枠組み。choice_data は {"action": 1〜5}）。
 * 3. resolve(): 一斉に公開（縦向きにする）。誰かが選んだアクション = このラウンドに行うフェイズ、
 *    各アクションを選んだプレイヤー = アクション選択ボーナスの保持者を決めて bonus_holders に保存する。
 */
final class PlanningPhase implements ActionStep
{
    public function __construct(private readonly ActionServices $services)
    {
    }

    /**
     * 回収（21）。第 1 ラウンドは全員が手札なので何も変わらない。
     */
    public function collect(): void
    {
        $this->services->actionCards->retrieve();
    }

    /**
     * 選べるアクション（手札にあるもの。昇順）。横向きのカードは含まない。I は毎ラウンド手札に戻るので常に含まれる。
     *
     * @return list<int>
     */
    public function selectable(int $playerId): array
    {
        return $this->services->actionCards->handOf($playerId);
    }

    public function participants(StepMode $mode): array
    {
        return $this->services->players->playerIds();
    }

    public function canAct(int $playerId, StepMode $mode): bool
    {
        return $this->selectable($playerId) !== [];
    }

    public function validate(int $playerId, StepMode $mode, array $data): void
    {
        $action = $data['action'] ?? null;
        if (!is_int($action) || !in_array($action, ActionCardRepository::ACTION_NUMBERS, true)) {
            throw new RuleViolation('Select an action card');
        }
        if (!in_array($action, $this->selectable($playerId), true)) {
            throw new RuleViolation('Used last round. Returns to your hand next round.');
        }
    }

    /** zombie: I 予算発行（常に選べて、コストも失敗のリスクもない。008 §3-1） */
    public function defaultChoice(int $playerId, StepMode $mode): array
    {
        return ['action' => ActionCardRepository::BUDGET_ACTION];
    }

    /**
     * 公開（23）。全員分の選択を縦向きにし、選ばれたフェイズと保持者を保存する。
     */
    public function resolve(StepMode $mode, array $choices): StepResult
    {
        $order = $this->services->players->playerIds();
        $plots = [];
        foreach ($order as $playerId) {
            $data = $choices[$playerId] ?? $this->defaultChoice($playerId, $mode);
            $this->validate($playerId, $mode, $data);
            $plots[$playerId] = (int) $data['action'];
        }
        $holders = [];
        $outcomes = [];
        foreach ($plots as $playerId => $action) {
            $this->services->actionCards->reveal($playerId, $action);
            $holders[$action][] = $playerId;
            $outcomes[] = new StepOutcome($playerId, 'plot', false, ['action' => $action]);
        }
        ksort($holders);
        $this->services->globals->setBonusHolders($holders);
        return new StepResult($outcomes, ['phases' => array_keys($holders)]);
    }

    /**
     * このラウンドに行うアクション（昇順 = I → V の順）。公開後に使う。
     *
     * @return list<int>
     */
    public function phasesToRun(): array
    {
        return $this->services->globals->selectedActions();
    }

    /**
     * $after の次に行うアクション。$after が null なら最初。残りがなければ null（循環フェイズへ。009 の 40）。
     */
    public function nextPhase(?int $after): ?int
    {
        foreach ($this->phasesToRun() as $action) {
            if ($after === null || $action > $after) {
                return $action;
            }
        }
        return null;
    }
}
