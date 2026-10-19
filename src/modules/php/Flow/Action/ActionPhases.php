<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

use Bga\Games\ProjectEdgeworth\Board\RandomSource;
use Bga\Games\ProjectEdgeworth\Data\Database;
use Bga\Games\ProjectEdgeworth\Data\GlobalValues;

/**
 * 計画フェイズとアクションフェイズ I〜V のルール層の入口（I-08 の BGA に依存しない部分）。
 * 状態クラス（I-07）は ActionPhases::load() で作り、状態ごとに次のように呼ぶ。
 *
 *   21 PlanningCollect  → planning()->collect()
 *   22 PlanningPlot     → runner()->enter / confirm / zombie（step = planning()）
 *   23 PlanningReveal   → runner()->resolve(planning(), 22) → planning()->nextPhase(null)
 *   50 BudgetDriver     → budget()->applyMain() / applyBonus()
 *   60/61/62 配備       → runner() と deploy()（メイン 61、ボーナス 62。StepMode::Main / Bonus）
 *   70/71/72 打ち上げ   → runner() と launch()
 *   80/81/82 通信確立   → runner() と link()
 *   90/91 企画          → proposal()->drawAll()、runner() と proposal()
 *   actSell             → selling()->sell()
 */
final class ActionPhases
{
    private readonly PlanningPhase $planning;
    private readonly BudgetPhase $budget;
    private readonly DeployPhase $deploy;
    private readonly LaunchPhase $launch;
    private readonly LinkPhase $link;
    private readonly ProposalPhase $proposal;
    private readonly StepRunner $runner;

    public function __construct(private readonly ActionServices $services)
    {
        $this->planning = new PlanningPhase($services);
        $this->budget = new BudgetPhase($services);
        $this->deploy = new DeployPhase($services);
        $this->launch = new LaunchPhase($services);
        $this->link = new LinkPhase($services);
        $this->proposal = new ProposalPhase($services);
        $this->runner = new StepRunner($services->choices, $services->globals);
    }

    public static function load(
        Database $db,
        GlobalValues $globals,
        RandomSource $random,
        ?ActionHooks $hooks = null,
    ): self {
        return new self(ActionServices::load($db, $globals, $random, $hooks));
    }

    public function services(): ActionServices
    {
        return $this->services;
    }

    public function planning(): PlanningPhase
    {
        return $this->planning;
    }

    public function budget(): BudgetPhase
    {
        return $this->budget;
    }

    public function deploy(): DeployPhase
    {
        return $this->deploy;
    }

    public function launch(): LaunchPhase
    {
        return $this->launch;
    }

    public function link(): LinkPhase
    {
        return $this->link;
    }

    public function proposal(): ProposalPhase
    {
        return $this->proposal;
    }

    public function selling(): SellRules
    {
        return $this->services->selling;
    }

    public function runner(): StepRunner
    {
        return $this->runner;
    }

    /**
     * アクション番号（1〜5）に対応する同時選択のルール。
     */
    public function stepFor(int $actionNo): ActionStep
    {
        return match ($actionNo) {
            DeployPhase::ACTION => $this->deploy,
            LaunchPhase::ACTION => $this->launch,
            LinkPhase::ACTION => $this->link,
            ProposalPhase::ACTION => $this->proposal,
            default => throw new \InvalidArgumentException("action $actionNo has no player choice"),
        };
    }
}
