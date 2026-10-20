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
 * II 配備（001 §7-3、008 §3-6、009 の 60・61）。セットアップ手順 16（round_no = 0）も同じルール。
 *
 * 配備のアクション選択ボーナス（追加の配備 / 1 枚引く）は通常の配備と同時に選ぶ（作者回答 2026-10-20）。
 * 1 人が 1 回の選択で、通常の配備と、ボーナス保持者なら追加の配備または 1 枚引くをまとめて決める。
 *
 * choice_data（010 §9.3）:
 * - {"op": "deploy", "card": ID, "sat": sat_id, "sell": [ID, …], "useMods": [鍵, …], "extra": {…}} / {"op": "pass", "extra": {…}}
 * - extra（ボーナス保持者のみ）: {"op": "deploy", "card": ID, "sat": sat_id, "useMods": [鍵, …]} / {"op": "draw"}
 * - sell は 2 枚分の合計コストに対する売却。useMods は、その配備のカードのコストに使う分（外側が通常の配備、extra が追加の配備）
 *
 * 配備コスト = カードのコスト + 稼働中の衛星なら 5（1 枚ごと）− 効果による修正、下限 0。1 機 4 枚まで（2 枚を合わせて数える）。
 * 引いたカードは同時処理なので、この選択の配備には使えない。
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

    /** 配備のアクション選択ボーナスを持つか（セットアップは全員。EUROPA の宣言で得た人を含む） */
    public function isBonusHolder(int $playerId): bool
    {
        return in_array($playerId, $this->services->bonusHolders(self::ACTION), true);
    }

    /**
     * @param int $pending 同じ選択で先に同じ衛星へ配備するカードの枚数（追加の配備の 2 枚目は 1）
     * @param list<string>|null $mods 使う起動の修正の鍵。null なら今の設定のまま（フックの状態を変えない）
     */
    public function cost(int $playerId, int $cardNumber, Satellite $target, int $pending = 0, ?array $mods = null): DeployCost
    {
        $attached = $this->services->cards->countIn(CardLocation::Satellite, $target->id) + $pending;
        $hooks = $this->services->hooks;
        if ($mods !== null && $hooks instanceof UseModsAware) {
            $hooks->useMods($mods);
        }
        try {
            $reduction = $this->services->hooks->deployCostReduction($playerId, $target, $cardNumber, $attached);
        } finally {
            if ($mods !== null && $hooks instanceof UseModsAware) {
                $hooks->useMods([]);
            }
        }
        return new DeployCost(
            ActionServices::project($cardNumber)->cost,
            $target->isActive() ? self::ACTIVE_SURCHARGE : 0,
            max(0, $reduction),
        );
    }

    /**
     * そのカードを配備できる衛星の一覧（自分の衛星すべて。選べない衛星は blocked に理由が入る）。
     * $pending = 1 は「同じ選択で先に 1 枚その衛星へ配備する」場合（追加の配備が同じ衛星のとき）。
     *
     * @return list<DeployTarget>
     */
    public function targets(int $playerId, int $cardNumber, int $pending = 0): array
    {
        $targets = [];
        foreach ($this->services->satellites->forPlayer($playerId) as $sat) {
            $count = $this->services->cards->countIn(CardLocation::Satellite, $sat->id) + $pending;
            $cost = $this->cost($playerId, $cardNumber, $sat, $pending);
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
        return $this->services->players->playerIds();
    }

    public function canAct(int $playerId, StepMode $mode): bool
    {
        if ($this->isBonusHolder($playerId) && $this->canDraw()) {
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
        $plan = $this->plan($playerId, $data);
        if ($plan->steps !== []) {
            $this->services->selling->assertPayable($playerId, $plan->totalCost(), $plan->sell, $plan->cards());
        }
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
            array_push($outcomes, ...$this->resolveOne($playerId, $choices[$playerId]));
        }
        return new StepResult($outcomes);
    }

    /**
     * 配備（0〜2 枚）→ ドローの順。配備は 2 枚の合計コストを 1 回で払う。払えなければ配備だけ取りやめる（ドローは行う）。
     *
     * @param array<mixed> $data
     * @return list<StepOutcome>
     */
    private function resolveOne(int $playerId, array $data): array
    {
        $outcomes = [];
        $wantsDraw = $this->isBonusHolder($playerId) && is_array($data['extra'] ?? null) && ($data['extra']['op'] ?? null) === 'draw';
        $wantsDeploy = ($data['op'] ?? null) === 'deploy' || (is_array($data['extra'] ?? null) && ($data['extra']['op'] ?? null) === 'deploy');
        if ($wantsDeploy) {
            $outcomes = $this->deploy($playerId, $data);
        }
        if ($wantsDraw) {
            $outcomes[] = new StepOutcome($playerId, 'draw', false, [
                'cards' => $this->services->cards->drawTop($playerId, 1),
            ]);
        }
        return $outcomes === [] ? [new StepOutcome($playerId, 'pass')] : $outcomes;
    }

    /**
     * @param array<mixed> $data
     * @return list<StepOutcome>
     */
    private function deploy(int $playerId, array $data): array
    {
        try {
            $plan = $this->plan($playerId, $data);
        } catch (RuleViolation) {
            return [new StepOutcome($playerId, 'pass', true)];
        }
        if ($plan->steps === []) {
            return [];
        }
        $payment = $this->services->selling->tryPay($playerId, $plan->totalCost(), $plan->sell, $plan->cards());
        if ($payment === null) {
            return [new StepOutcome($playerId, 'pass', true)];
        }
        $outcomes = [];
        foreach ($plan->steps as $index => $step) {
            $tier = $this->services->cards->deploy($playerId, $step->card, $step->target->satellite->id);
            $outcomes[] = new StepOutcome($playerId, 'deploy', false, [
                'card' => $step->card,
                'sat' => $step->target->satellite->id,
                'tier' => $tier,
                'cost' => $step->target->cost->total(),
                // 売却は合計コストに対して 1 回。最初の配備にだけ載せる
                'sold' => $index === 0 ? $payment->soldCards : [],
                'active' => $step->target->satellite->isActive(),
                'mods' => $step->mods,
            ]);
        }
        return $outcomes;
    }

    /**
     * 選択の検証と、コストの見積もり。状態は変えない。
     *
     * @param array<mixed> $data
     * @throws RuleViolation
     */
    public function plan(int $playerId, array $data): DeployPlan
    {
        $op = $data['op'] ?? null;
        if ($op !== 'deploy' && $op !== 'pass') {
            throw new RuleViolation('Select a card and a satellite, or pass');
        }
        $steps = [];
        if ($op === 'deploy') {
            $steps[] = $this->stepFor($playerId, $data, []);
        }
        $draw = false;
        $extra = $data['extra'] ?? null;
        if ($extra !== null) {
            if (!is_array($extra)) {
                throw new RuleViolation('Invalid extra action');
            }
            if (!$this->isBonusHolder($playerId)) {
                throw new RuleViolation('You do not have the Deploy action bonus');
            }
            $extraOp = $extra['op'] ?? null;
            if ($extraOp === 'draw') {
                $draw = true;
            } elseif ($extraOp === 'deploy') {
                $steps[] = $this->stepFor($playerId, $extra, $steps);
            } elseif ($extraOp !== 'pass') {
                throw new RuleViolation('Choose another deployment or drawing 1 card');
            }
        }
        $sell = $steps === [] ? [] : $this->sellList($data);
        return new DeployPlan($steps, $draw, $sell);
    }

    /**
     * @param array<mixed> $part
     * @param list<DeployStep> $earlier 同じ選択で先に配備するカード
     * @throws RuleViolation
     */
    private function stepFor(int $playerId, array $part, array $earlier): DeployStep
    {
        $card = $part['card'] ?? null;
        $satId = $part['sat'] ?? null;
        if (!is_int($card) || !is_int($satId)) {
            throw new RuleViolation('Select a card and a satellite');
        }
        foreach ($earlier as $step) {
            if ($step->card === $card) {
                throw new RuleViolation("Card $card is already chosen");
            }
        }
        if (!in_array($card, $this->hand($playerId), true)) {
            throw new RuleViolation("Card $card is not in your hand");
        }
        $pending = count(array_filter($earlier, static fn(DeployStep $s): bool => $s->target->satellite->id === $satId));
        $mods = array_values(array_map('strval', (array) ($part['useMods'] ?? [])));
        foreach ($this->services->satellites->forPlayer($playerId) as $sat) {
            if ($sat->id !== $satId) {
                continue;
            }
            $count = $this->services->cards->countIn(CardLocation::Satellite, $sat->id) + $pending;
            if ($count >= ProjectCardRepository::MAX_DEPLOYED) {
                throw new RuleViolation('This satellite already has 4 cards');
            }
            if ($this->services->hooks->isForbidden(ForbiddenAction::DeployTo, $sat)) {
                throw new RuleViolation('Cards cannot be deployed to this satellite');
            }
            $cost = $this->cost($playerId, $card, $sat, $pending, $mods);
            $target = new DeployTarget($sat, $count, $cost, $this->services->selling->assess($playerId, $cost->total(), [$card]), null);
            return new DeployStep($card, $target, $mods);
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
