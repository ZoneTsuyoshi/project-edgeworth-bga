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
 * 追加の配備は、[Deploy another card] を押した時点で 1 枚目を先に確定（ステージ）してから 2 枚目を選ぶ（009 追補 2026-11-12）。
 *
 * choice_data（010 §9.3）:
 * - 未ステージ（S0）: {"op": "deploy", "card": ID, "sat": sat_id, "sell": [ID, …], "useMods": [鍵, …], "extra": {"op": "draw"}} /
 *   {"op": "pass", "extra": {…}}。extra はボーナス保持者のみ: {"op": "draw"} / {"op": "pass"}（追加の配備は S0 では選べない）
 * - ステージ済み（S1）: {"op": "staged", "extra": {"op": "deploy", "card": ID, "sat": sat_id, "sell": [ID, …], "useMods": [鍵, …]}}
 *   または {"op": "staged"}（2 枚目なし）。1 枚目はステージで配備・支払い済みなので、外側の op と 1 枚目の引数は無視する
 * - sell と useMods は、その配備のカードのコストに使う分（1 枚ごとに別々。合計コストへの売却は廃止）
 *
 * 配備コスト = カードのコスト + 稼働中の衛星なら 5（1 枚ごと）− 効果による修正、下限 0。1 機 4 枚まで。
 * コストは実際の盤面を見て出す（2 枚目は、ステージ済みの 1 枚目が衛星に付いた状態。P010・P012・P026 などが効く）。
 * 通常の配備（S0）で引いたカードは同時処理なのでその選択には使えない。ステージ時の効果で引いたカードは 2 枚目に使える（Q-24）。
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
     * @param list<string>|null $mods 使う起動の修正の鍵。null なら今の設定のまま（フックの状態を変えない）
     */
    public function cost(int $playerId, int $cardNumber, Satellite $target, ?array $mods = null): DeployCost
    {
        $attached = $this->services->cards->countIn(CardLocation::Satellite, $target->id);
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
     * そのカードを配備できる衛星の一覧（自分の衛星すべて。選べない衛星は blocked に理由が入る）。実際の盤面を見る
     * （ステージ済みの 1 枚目は衛星に付いているので、2 枚目の候補はそれを数える）。
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

    /** 1 枚目をステージ済み（S1）か */
    public function isStaged(int $playerId): bool
    {
        return $this->services->deployStages->isStaged($playerId);
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
        if ($this->isStaged($playerId)) {
            $plan = $this->planSecond($playerId, $data);
            if ($plan->steps !== []) {
                $this->services->selling->assertPayable($playerId, $plan->totalCost(), $plan->sell, $plan->cards());
            }
            return;
        }
        $plan = $this->plan($playerId, $data);
        if ($plan->steps !== []) {
            $this->services->selling->assertPayable($playerId, $plan->totalCost(), $plan->sell, $plan->cards());
        }
    }

    /** zombie: S0 なら [Pass]（配備しない。ドローもしない）。S1 ならステージ済みの 1 枚目を残して 2 枚目なしで確定（009 追補 8） */
    public function defaultChoice(int $playerId, StepMode $mode): array
    {
        return $this->isStaged($playerId) ? ['op' => 'staged'] : ['op' => 'pass'];
    }

    /**
     * 確定内容の形を整える。ステージ済み（S1）の人は、外側の op と 1 枚目の引数を捨て、extra（2 枚目）だけを残す（009 追補 3）。
     *
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function normalize(int $playerId, array $data): array
    {
        if (!$this->isStaged($playerId)) {
            return $data;
        }
        $extra = $data['extra'] ?? null;

        return is_array($extra) && $extra !== [] ? ['op' => 'staged', 'extra' => $extra] : ['op' => 'staged'];
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
     * S0: 配備（0〜1 枚）→ ドローの順。払えなければ配備だけ取りやめる（ドローは行う）。
     * S1: ステージ済みの 1 枚目は飛ばし、2 枚目だけを処理する（再計算も再支払いもしない。払えなければ 2 枚目だけ取りやめる）。
     *
     * @param array<mixed> $data
     * @return list<StepOutcome>
     */
    private function resolveOne(int $playerId, array $data): array
    {
        if ($this->isStaged($playerId)) {
            return $this->resolveSecond($playerId, $data);
        }
        $outcomes = [];
        $wantsDraw = $this->isBonusHolder($playerId) && is_array($data['extra'] ?? null) && ($data['extra']['op'] ?? null) === 'draw';
        if (($data['op'] ?? null) === 'deploy') {
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
    private function resolveSecond(int $playerId, array $data): array
    {
        try {
            $plan = $this->planSecond($playerId, $data);
        } catch (RuleViolation) {
            return [];
        }
        if ($plan->steps === []) {
            return [];
        }
        $outcome = $this->commitStep($playerId, $plan->steps[0], $plan->sell);

        return $outcome === null ? [] : [$outcome];
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
        $outcome = $this->commitStep($playerId, $plan->steps[0], $plan->sell);

        return [$outcome ?? new StepOutcome($playerId, 'pass', true)];
    }

    /**
     * 1 枚分の支払い（売却の同梱を含む）と配備。払えなければ何もせず null。
     *
     * @param list<int> $sell
     */
    private function commitStep(int $playerId, DeployStep $step, array $sell): ?StepOutcome
    {
        $payment = $this->services->selling->tryPay($playerId, $step->target->cost->total(), $sell, [$step->card]);
        if ($payment === null) {
            return null;
        }
        $tier = $this->services->cards->deploy($playerId, $step->card, $step->target->satellite->id);

        return new StepOutcome($playerId, 'deploy', false, [
            'card' => $step->card,
            'sat' => $step->target->satellite->id,
            'tier' => $tier,
            'cost' => $step->target->cost->total(),
            'sold' => $payment->soldCards,
            'active' => $step->target->satellite->isActive(),
            'mods' => $step->mods,
        ]);
    }

    /**
     * [Deploy another card]（`actStageDeploy`）: 1 枚目の検証 → 支払い（売却の同梱を含む）→ 配備。ボーナス保持者だけ。
     * 失敗は `RuleViolation` で、状態を変えない。DEPLOYED イベントの記録・効果の解決・ステージ記録の保存は呼び出し側（`DeployStageFlow`）。
     *
     * @param array<mixed> $data {op: deploy, card, sat, sell?, useMods?}（extra は無視する）
     * @throws RuleViolation
     */
    public function stageFirst(int $playerId, array $data): StepOutcome
    {
        if (!$this->isBonusHolder($playerId)) {
            throw new RuleViolation('You do not have the Deploy action bonus');
        }
        if ($this->isStaged($playerId)) {
            throw new RuleViolation('The first card is already deployed');
        }
        if (($data['op'] ?? null) !== 'deploy') {
            throw new RuleViolation('Select a card and a satellite');
        }
        unset($data['extra']);
        $plan = $this->plan($playerId, $data);
        $this->services->selling->assertPayable($playerId, $plan->totalCost(), $plan->sell, $plan->cards());

        return $this->commitStep($playerId, $plan->steps[0], $plan->sell) ?? throw new RuleViolation('Not enough Maya');
    }

    /**
     * 未ステージ（S0）の選択の検証と、コストの見積もり。状態は変えない。追加の配備は S0 では選べない
     * （[Deploy another card] で 1 枚目を先に確定する）。extra は [Draw 1 card] / [Pass] のみ。
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
            $steps[] = $this->stepFor($playerId, $data);
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
                throw new RuleViolation('Press [Deploy another card] to deploy the first card before choosing another');
            } elseif ($extraOp !== 'pass') {
                throw new RuleViolation('Choose another deployment or drawing 1 card');
            }
        }
        $sell = $steps === [] ? [] : $this->sellList($data);
        return new DeployPlan($steps, $draw, $sell);
    }

    /**
     * ステージ済み（S1）の 2 枚目の検証と見積もり。`extra` が無い・{op: pass} なら 2 枚目なし。状態は変えない。
     * 2 枚目は実際の盤面（ステージ済みの 1 枚目が衛星に付いた状態）で数え、コストは 2 枚目だけの額。売却も 2 枚目の分。
     *
     * @param array<mixed> $data 確定内容（外側の op と 1 枚目の引数は無視する）
     * @throws RuleViolation
     */
    public function planSecond(int $playerId, array $data): DeployPlan
    {
        $extra = $data['extra'] ?? null;
        if ($extra === null || $extra === []) {
            return new DeployPlan([], false, []);
        }
        if (!is_array($extra)) {
            throw new RuleViolation('Invalid extra action');
        }
        $op = $extra['op'] ?? null;
        if ($op === 'pass') {
            return new DeployPlan([], false, []);
        }
        if ($op !== 'deploy') {
            throw new RuleViolation('Choose another deployment, or confirm without one');
        }
        $step = $this->stepFor($playerId, $extra);

        return new DeployPlan([$step], false, $this->sellList($extra));
    }

    /**
     * @param array<mixed> $part
     * @throws RuleViolation
     */
    private function stepFor(int $playerId, array $part): DeployStep
    {
        $card = $part['card'] ?? null;
        $satId = $part['sat'] ?? null;
        if (!is_int($card) || !is_int($satId)) {
            throw new RuleViolation('Select a card and a satellite');
        }
        if (!in_array($card, $this->hand($playerId), true)) {
            throw new RuleViolation("Card $card is not in your hand");
        }
        $mods = array_values(array_map('strval', (array) ($part['useMods'] ?? [])));
        foreach ($this->services->satellites->forPlayer($playerId) as $sat) {
            if ($sat->id !== $satId) {
                continue;
            }
            $count = $this->services->cards->countIn(CardLocation::Satellite, $sat->id);
            if ($count >= ProjectCardRepository::MAX_DEPLOYED) {
                throw new RuleViolation('This satellite already has 4 cards');
            }
            if ($this->services->hooks->isForbidden(ForbiddenAction::DeployTo, $sat)) {
                throw new RuleViolation('Cards cannot be deployed to this satellite');
            }
            $cost = $this->cost($playerId, $card, $sat, $mods);
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
