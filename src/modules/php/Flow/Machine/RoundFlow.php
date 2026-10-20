<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Data\ActionCardState;
use Bga\Games\projectedgeworth\Data\FlowPhase;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Flow\StateId;

/**
 * ラウンドの開始・計画フェイズ・アクションフェイズの進行の状態（20・21・23・40。009 §3.4〜§3.6）の処理。
 */
final class RoundFlow
{
    /** アクション番号 => 40 の遷移名 */
    private const PHASE_TRANSITIONS = [1 => 'budget', 2 => 'deploy', 3 => 'launch', 4 => 'link', 5 => 'proposal'];

    /** アクション番号 => flow_phase */
    private const PHASES = [
        1 => FlowPhase::Budget,
        2 => FlowPhase::Deploy,
        3 => FlowPhase::LaunchTurn,
        4 => FlowPhase::Link,
        5 => FlowPhase::Proposal,
    ];

    public function __construct(private readonly FlowContext $ctx)
    {
    }

    /**
     * 20: ラウンド番号を +1 し、起動効果の使用済みを全員分戻し、着陸猶予が残っていれば終わらせ、ラウンド単位のデータを初期化する（009 §7）。
     * 進行度は状態の updateGameProgression が Game::getGameProgression を呼んで更新する。
     */
    public function start(): DriverResult
    {
        $globals = $this->ctx->globals;
        $services = $this->ctx->services;
        $round = $globals->roundNo() + 1;
        $globals->setRoundNo($round);
        $globals->setFlowPhase(FlowPhase::Planning);
        $globals->setFlowStep(0);
        $globals->setFlowContext([]);
        $globals->setBonusHolders([]);
        $globals->setStepParticipants([]);
        $globals->setWindowId(null);
        $globals->setWindowResume(null);
        $globals->setWindowGen(0);
        $globals->setWindowEffects(0);
        $globals->setWindowSnapshot(null);
        $services->satellites->resetActivations();
        $services->cards->resetActivations();
        $services->satellites->clearAllGrace();
        return new DriverResult('plan', [$this->ctx->notices->roundStarted($round)]);
    }

    /**
     * 21: アクションカードの回収（001 §6-1 の 1-1〜1-3）。窓 PL1 を開く対象があれば窓へ（戻り先は 22）。
     */
    public function collect(): DriverResult
    {
        $actionCards = $this->ctx->services->actionCards;
        $before = $actionCards->all();
        $this->ctx->phases->planning()->collect();
        $after = $actionCards->all();

        $moves = [];
        foreach ($after as $playerId => $states) {
            foreach ($states as $actionNo => $state) {
                $was = $before[$playerId][$actionNo] ?? $state;
                if ($was === $state) {
                    continue;
                }
                $key = $state === ActionCardState::Hand ? 'toHand' : 'toSideways';
                $moves[$playerId]['toHand'] ??= [];
                $moves[$playerId]['toSideways'] ??= [];
                $moves[$playerId][$key][] = $actionNo;
            }
        }
        $notices = $moves === [] ? [] : [$this->ctx->notices->actionCardsCollected($moves)];

        if ($this->ctx->gate->needsWindow('PL1')) {
            $this->ctx->globals->setWindowId('PL1');
            $this->ctx->globals->setWindowResume(StateId::PLANNING_PLOT);
            return new DriverResult('window', $notices);
        }
        return new DriverResult('plot', $notices);
    }

    /**
     * 23: 全員のプロットを一斉に公開し、選ばれたアクションと保持者を確定する。窓 PL2 を開く対象があれば窓へ（戻り先は 40）。
     */
    public function reveal(): DriverResult
    {
        $result = $this->ctx->phases->runner()->resolve($this->ctx->phases->planning(), StateId::PLANNING_PLOT, StepMode::Main);
        $notices = (new OutcomeNotices($this->ctx))->reveal($result);
        if ($this->ctx->gate->needsWindow('PL2')) {
            $this->ctx->globals->setWindowId('PL2');
            $this->ctx->globals->setWindowResume(StateId::ACTION_PHASE_ROUTER);
            return new DriverResult('window', $notices);
        }
        return new DriverResult('next', $notices);
    }

    /**
     * 40: 選ばれたアクションフェイズを I → V の順に 1 つずつ進める。セットアップ（round_no = 0）は II 配備だけ。
     * 現在のフェイズが終わって戻ってきたとき、次に選ばれたフェイズへ。残りがなければ循環フェイズへ（セットアップは 20 へ）。
     */
    public function route(): DriverResult
    {
        $globals = $this->ctx->globals;
        $planning = $this->ctx->phases->planning();
        $current = $globals->flowPhase();

        if ($globals->roundNo() === 0) {
            return $current === FlowPhase::Setup ? $this->enterPhase(2) : new DriverResult('round');
        }

        $after = match ($current) {
            FlowPhase::Planning => null,
            FlowPhase::Budget => 1,
            FlowPhase::Deploy => 2,
            FlowPhase::LaunchTurn => 3,
            FlowPhase::Link => 4,
            FlowPhase::Proposal => 5,
            default => throw new \LogicException("unexpected flow_phase at the router: {$current->value}"),
        };
        $next = $planning->nextPhase($after);
        if ($next === null) {
            $globals->setFlowPhase(FlowPhase::Orbit);
            $globals->setFlowStep(0);
            $globals->setFlowContext([]);
            $globals->setStepParticipants([]);
            return new DriverResult('orbit', [$this->ctx->notices->phaseStarted('orbit', [])]);
        }
        return $this->enterPhase($next);
    }

    private function enterPhase(int $actionNo): DriverResult
    {
        $globals = $this->ctx->globals;
        $globals->setFlowPhase(self::PHASES[$actionNo]);
        $globals->setFlowStep(0);
        $globals->setFlowContext([]);
        $globals->setStepParticipants([]);
        $holders = $this->ctx->services->bonusHolders($actionNo);
        return new DriverResult(self::PHASE_TRANSITIONS[$actionNo], [$this->ctx->notices->phaseStarted($actionNo, $holders)]);
    }
}
