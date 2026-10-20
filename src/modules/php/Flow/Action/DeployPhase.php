<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Data\ProjectCardRepository;
use Bga\Games\projectedgeworth\Data\Satellite;

/**
 * II 配備（001 §7-3、008 §3-6・§3-7、009 の 60〜62）。セットアップ手順 16（round_no = 0）も同じルール。
 *
 * choice_data（010 §9.3）:
 * - メイン: {"op": "deploy", "card": ID, "sat": sat_id, "sell": [ID, …]} / {"op": "pass"}
 * - ボーナス: 上に加えて {"op": "draw"}（山札から 1 枚引く）
 *
 * 配備コスト = カードのコスト + 稼働中の衛星なら 5 − 効果による修正、下限 0。1 機 4 枚まで。
 * 配備時の効果（DEPLOYED イベント）は効果エンジンの役目で、ここでは配備の結果（outcome）を返すだけ。
 */
final class DeployPhase implements ActionStep
{
    public const ACTION = 2;
    public const ACTIVE_SURCHARGE = 5;

    public function __construct(private readonly ActionServices $services)
    {
    }

    /**
     * 手札のプロジェクトカード（ID の昇順ではなく手札の順）。
     *
     * @return list<int>
     */
    public function hand(int $playerId): array
    {
        return $this->services->cards->idsIn(CardLocation::Hand, $playerId);
    }

    public function cost(int $playerId, int $cardNumber, Satellite $target): DeployCost
    {
        return new DeployCost(
            ActionServices::project($cardNumber)->cost,
            $target->isActive() ? self::ACTIVE_SURCHARGE : 0,
            max(0, $this->services->hooks->deployCostReduction(
                $playerId,
                $target,
                $cardNumber,
                $this->services->cards->countIn(CardLocation::Satellite, $target->id),
            )),
        );
    }

    /**
     * そのカードを配備できる衛星の一覧（自分の衛星すべて。選べない衛星は blocked に理由が入る）。
     *
     * @return list<DeployTarget>
     */
    public function targets(int $playerId, int $cardNumber): array
    {
        $targets = [];
        foreach ($this->services->satellites->forPlayer($playerId) as $sat) {
            $count = $this->services->cards->countIn(CardLocation::Satellite, $sat->id);
            $cost = $this->cost($playerId, $cardNumber, $sat);
            $blocked = null;
            if ($count >= ProjectCardRepository::MAX_DEPLOYED) {
                $blocked = DeployTarget::BLOCKED_FULL;
            } elseif ($this->services->hooks->isForbidden(ForbiddenAction::DeployTo, $sat)) {
                $blocked = DeployTarget::BLOCKED_FORBIDDEN;
            }
            $targets[] = new DeployTarget(
                $sat,
                $count,
                $cost,
                $this->services->selling->assess($playerId, $cost->total(), [$cardNumber]),
                $blocked,
            );
        }
        return $targets;
    }

    public function participants(StepMode $mode): array
    {
        if ($mode === StepMode::Main || $this->services->globals->roundNo() === 0) {
            return $this->services->players->playerIds();
        }
        return $this->services->bonusHolders(self::ACTION);
    }

    public function canAct(int $playerId, StepMode $mode): bool
    {
        if ($mode === StepMode::Bonus && $this->canDraw()) {
            return true;
        }
        foreach ($this->hand($playerId) as $cardNumber) {
            foreach ($this->targets($playerId, $cardNumber) as $target) {
                if ($target->isSelectable()) {
                    return true;
                }
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
        if ($op === 'draw') {
            if ($mode !== StepMode::Bonus) {
                throw new RuleViolation('Drawing is only available as the action bonus');
            }
            return;
        }
        if ($op !== 'deploy') {
            throw new RuleViolation('Select a card and a satellite, or pass');
        }
        $card = $data['card'] ?? null;
        $satId = $data['sat'] ?? null;
        if (!is_int($card) || !is_int($satId)) {
            throw new RuleViolation('Select a card and a satellite');
        }
        $sell = $this->sellList($data);
        $target = $this->targetFor($playerId, $card, $satId);
        $this->services->selling->assertPayable($playerId, $target->cost->total(), $sell, [$card]);
    }

    /** zombie: [Pass]（配備しない。ドローもしない。008 §3-6・§3-7） */
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
            if ($op === 'draw' && $mode === StepMode::Bonus) {
                $outcomes[] = new StepOutcome($playerId, 'draw', false, [
                    'cards' => $this->services->cards->drawTop($playerId, 1),
                ]);
            } elseif ($op === 'deploy') {
                $outcomes[] = $this->deploy($playerId, $data);
            } else {
                $outcomes[] = new StepOutcome($playerId, 'pass');
            }
        }
        return new StepResult($outcomes);
    }

    /**
     * @param array<mixed> $data
     */
    private function deploy(int $playerId, array $data): StepOutcome
    {
        $card = (int) ($data['card'] ?? 0);
        $satId = (int) ($data['sat'] ?? 0);
        try {
            $target = $this->targetFor($playerId, $card, $satId);
        } catch (RuleViolation) {
            return new StepOutcome($playerId, 'pass', true);
        }
        $payment = $this->services->selling->tryPay($playerId, $target->cost->total(), $this->sellList($data), [$card]);
        if ($payment === null) {
            return new StepOutcome($playerId, 'pass', true);
        }
        $tier = $this->services->cards->deploy($playerId, $card, $satId);
        return new StepOutcome($playerId, 'deploy', false, [
            'card' => $card,
            'sat' => $satId,
            'tier' => $tier,
            'cost' => $payment->cost,
            'sold' => $payment->soldCards,
            'active' => $target->satellite->isActive(),
        ]);
    }

    /**
     * @throws RuleViolation
     */
    private function targetFor(int $playerId, int $cardNumber, int $satId): DeployTarget
    {
        if (!in_array($cardNumber, $this->hand($playerId), true)) {
            throw new RuleViolation("Card $cardNumber is not in your hand");
        }
        foreach ($this->targets($playerId, $cardNumber) as $target) {
            if ($target->satellite->id !== $satId) {
                continue;
            }
            if ($target->blocked === DeployTarget::BLOCKED_FULL) {
                throw new RuleViolation('This satellite already has 4 cards');
            }
            if ($target->blocked === DeployTarget::BLOCKED_FORBIDDEN) {
                throw new RuleViolation('Cards cannot be deployed to this satellite');
            }
            return $target;
        }
        throw new RuleViolation("Satellite $satId is not yours");
    }

    private function canDraw(): bool
    {
        return $this->services->cards->countIn(CardLocation::Deck) + $this->services->cards->countIn(CardLocation::Discard) > 0;
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
