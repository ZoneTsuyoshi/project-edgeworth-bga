<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

use Bga\Games\ProjectEdgeworth\Board\Direction;
use Bga\Games\ProjectEdgeworth\Data\CardLocation;
use Bga\Games\ProjectEdgeworth\Data\Satellite;
use Bga\Games\ProjectEdgeworth\Material\SatelliteCards;

/**
 * III 打ち上げ・転回（001 §7-4、008 §3-2・§3-3、009 の 70〜72）。
 *
 * choice_data（010 §9.3）。向きは NE / SE / NW / SW（斜め 4 方向のみ。003 §4.1）:
 * - メイン: {"op": "launch", "sat": id, "dir": "NE", "sell": [ID, …]} / {"op": "turn", "sat": id, "dir": "SW"} / {"op": "pass"}
 * - ボーナス: {"op": "turn", …} / {"op": "budget"} / {"op": "pass"}（打ち上げは選べない）
 *
 * 打ち上げの条件: 非稼働、プロジェクトカードが 1 枚以上配備、自分の発射拠点がある。コストは衛星カードの打ち上げコスト − 修正（下限 0）。
 * 転回・予算上昇は、置く非稼働の衛星コマが要るため、非稼働の衛星が 1 機以上あることが条件（008 の暫定 No.6）。転回にコストはない。
 */
final class LaunchPhase implements ActionStep
{
    public const ACTION = 3;
    public const BUDGET_GAIN = 1;

    public function __construct(private readonly ActionServices $services)
    {
    }

    /**
     * 打ち上げの候補（自分の衛星すべて。選べない衛星は blocked に理由が入る）。
     *
     * @return list<LaunchOption>
     */
    public function launchOptions(int $playerId): array
    {
        $options = [];
        $hasPad = $this->services->players->padCell($playerId) !== null;
        foreach ($this->services->satellites->forPlayer($playerId) as $sat) {
            $count = $this->services->cards->countIn(CardLocation::Satellite, $sat->id);
            $cost = $this->launchCost($playerId, $sat);
            $blocked = null;
            if ($sat->isActive()) {
                $blocked = LaunchOption::BLOCKED_ACTIVE;
            } elseif ($count < 1) {
                $blocked = LaunchOption::BLOCKED_NO_PROJECT;
            } elseif (!$hasPad) {
                $blocked = LaunchOption::BLOCKED_NO_PAD;
            }
            $options[] = new LaunchOption($sat, $count, $cost, $this->services->selling->assess($playerId, $cost), $blocked);
        }
        return $options;
    }

    public function launchCost(int $playerId, Satellite $sat): int
    {
        $base = SatelliteCards::get($sat->cardId)->launchCost;
        return max(0, $base - max(0, $this->services->hooks->launchCostReduction($playerId, $sat)));
    }

    /**
     * 転回の候補（稼働中の自分の衛星）。非稼働の衛星が 1 機もいなければすべて選べない。
     *
     * @return list<TurnOption>
     */
    public function turnOptions(int $playerId): array
    {
        $noInactive = !$this->hasInactive($playerId);
        $options = [];
        foreach ($this->services->satellites->forPlayer($playerId) as $sat) {
            if (!$sat->isActive()) {
                continue;
            }
            $blocked = null;
            if ($noInactive) {
                $blocked = TurnOption::BLOCKED_NO_INACTIVE;
            } elseif ($this->services->hooks->isForbidden(ForbiddenAction::Turn, $sat)) {
                $blocked = TurnOption::BLOCKED_FORBIDDEN;
            }
            $options[] = new TurnOption($sat, $blocked);
        }
        return $options;
    }

    /** 予算上昇（ボーナス）を選べるか = 非稼働の衛星が 1 機以上 */
    public function canRaiseBudget(int $playerId): bool
    {
        return $this->hasInactive($playerId);
    }

    public function participants(StepMode $mode): array
    {
        return $mode === StepMode::Main ? $this->services->players->playerIds() : $this->services->bonusHolders(self::ACTION);
    }

    public function canAct(int $playerId, StepMode $mode): bool
    {
        if ($mode === StepMode::Main) {
            foreach ($this->launchOptions($playerId) as $option) {
                if ($option->isSelectable()) {
                    return true;
                }
            }
        } elseif ($this->canRaiseBudget($playerId)) {
            return true;
        }
        foreach ($this->turnOptions($playerId) as $option) {
            if ($option->isSelectable()) {
                return true;
            }
        }
        return false;
    }

    public function validate(int $playerId, StepMode $mode, array $data): void
    {
        $op = $data['op'] ?? null;
        if ($op === 'pass') {
            return;
        }
        if ($op === 'budget') {
            if ($mode !== StepMode::Bonus) {
                throw new RuleViolation('The budget increase is only available as the action bonus');
            }
            if (!$this->canRaiseBudget($playerId)) {
                throw new RuleViolation('No inactive satellite to place');
            }
            return;
        }
        if ($op === 'turn') {
            $this->turnTarget($playerId, $data);
            return;
        }
        if ($op === 'launch') {
            if ($mode !== StepMode::Main) {
                throw new RuleViolation('Launching is not available as the action bonus');
            }
            $option = $this->launchTarget($playerId, $data);
            $this->services->selling->assertPayable($playerId, $option->cost, $this->sellList($data));
            return;
        }
        throw new RuleViolation('Select an operation');
    }

    /** zombie: [Pass]（コストを払わず、衛星を動かさない。008 §3-2） */
    public function defaultChoice(int $playerId, StepMode $mode): array
    {
        return ['op' => 'pass'];
    }

    public function resolve(StepMode $mode, array $choices): StepResult
    {
        $outcomes = [];
        foreach ($this->services->seatOrder(array_keys($choices)) as $playerId) {
            $data = $choices[$playerId];
            $op = $data['op'] ?? 'pass';
            if ($op === 'launch' && $mode === StepMode::Main) {
                $outcomes[] = $this->launch($playerId, $data);
            } elseif ($op === 'turn') {
                $outcomes[] = $this->turn($playerId, $data);
            } elseif ($op === 'budget' && $mode === StepMode::Bonus && $this->canRaiseBudget($playerId)) {
                $budget = $this->services->players->addBudget($playerId, self::BUDGET_GAIN);
                $outcomes[] = new StepOutcome($playerId, 'budget', false, ['budget' => $budget]);
            } else {
                $outcomes[] = new StepOutcome($playerId, 'pass');
            }
        }
        return new StepResult($outcomes);
    }

    /**
     * @param array<mixed> $data
     */
    private function launch(int $playerId, array $data): StepOutcome
    {
        try {
            $option = $this->launchTarget($playerId, $data);
        } catch (RuleViolation) {
            return new StepOutcome($playerId, 'pass', true);
        }
        $payment = $this->services->selling->tryPay($playerId, $option->cost, $this->sellList($data));
        if ($payment === null) {
            return new StepOutcome($playerId, 'pass', true);
        }
        $cell = (int) $this->services->players->padCell($playerId);
        $direction = Direction::from((string) $data['dir']);
        $this->services->satellites->launch($option->satellite->id, $cell, $direction);
        return new StepOutcome($playerId, 'launch', false, [
            'sat' => $option->satellite->id,
            'cell' => $cell,
            'dir' => $direction->value,
            'cost' => $payment->cost,
            'sold' => $payment->soldCards,
        ]);
    }

    /**
     * @param array<mixed> $data
     */
    private function turn(int $playerId, array $data): StepOutcome
    {
        try {
            $option = $this->turnTarget($playerId, $data);
        } catch (RuleViolation) {
            return new StepOutcome($playerId, 'pass', true);
        }
        $from = $option->satellite->direction;
        $to = Direction::from((string) $data['dir']);
        $this->services->satellites->turn($option->satellite->id, $to);
        return new StepOutcome($playerId, 'turn', false, [
            'sat' => $option->satellite->id,
            'from' => $from?->value,
            'dir' => $to->value,
            'same' => $from === $to,
        ]);
    }

    /**
     * @param array<mixed> $data
     * @throws RuleViolation
     */
    private function launchTarget(int $playerId, array $data): LaunchOption
    {
        $this->direction($data);
        $satId = $data['sat'] ?? null;
        foreach ($this->launchOptions($playerId) as $option) {
            if ($option->satellite->id !== $satId) {
                continue;
            }
            match ($option->blocked) {
                LaunchOption::BLOCKED_ACTIVE => throw new RuleViolation('This satellite is already in operation'),
                LaunchOption::BLOCKED_NO_PROJECT => throw new RuleViolation('No Project cards deployed'),
                LaunchOption::BLOCKED_NO_PAD => throw new RuleViolation('You have no launch base'),
                default => null,
            };
            return $option;
        }
        throw new RuleViolation('Select a satellite to launch');
    }

    /**
     * @param array<mixed> $data
     * @throws RuleViolation
     */
    private function turnTarget(int $playerId, array $data): TurnOption
    {
        $this->direction($data);
        $satId = $data['sat'] ?? null;
        foreach ($this->turnOptions($playerId) as $option) {
            if ($option->satellite->id !== $satId) {
                continue;
            }
            match ($option->blocked) {
                TurnOption::BLOCKED_NO_INACTIVE => throw new RuleViolation('No inactive satellite to place'),
                TurnOption::BLOCKED_FORBIDDEN => throw new RuleViolation('This satellite cannot be turned'),
                default => null,
            };
            return $option;
        }
        throw new RuleViolation('Select an active satellite to turn');
    }

    /**
     * @param array<mixed> $data
     * @throws RuleViolation
     */
    private function direction(array $data): Direction
    {
        $direction = Direction::tryFrom((string) ($data['dir'] ?? ''));
        if ($direction === null || !$direction->isDiagonal()) {
            throw new RuleViolation('Select a direction (NE, SE, NW or SW)');
        }
        return $direction;
    }

    private function hasInactive(int $playerId): bool
    {
        foreach ($this->services->satellites->forPlayer($playerId) as $sat) {
            if (!$sat->isActive()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<mixed> $data
     * @return list<int>
     */
    private function sellList(array $data): array
    {
        $sell = $data['sell'] ?? [];
        if (!is_array($sell)) {
            throw new RuleViolation('Invalid sale');
        }
        return array_values(array_map('intval', $sell));
    }
}
