<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Flow;

use Bga\Games\projectedgeworth\Flow\StateId;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\StateGraph;

/** 009 §3 の状態一覧・ID・遷移と、States/ の状態クラスが一致していること。 */
final class StateGraphTest extends TestCase
{
    /** 009 §3.3〜§3.9 の表から書き起こした期待値（ドラフトの 15・16 は初版では作らない） */
    private const EXPECTED = [
        10 => ['SetupLaunchPadNext', 'GAME', ['place' => 11, 'done' => 12]],
        11 => ['SetupLaunchPadPlace', 'ACTIVE_PLAYER', ['placed' => 10]],
        12 => ['SetupHandRoute', 'GAME', ['mulligan' => 13, 'none' => 17]],
        13 => ['SetupMulligan', 'MULTIPLE_ACTIVE_PLAYER', ['allConfirmed' => 14]],
        14 => ['SetupMulliganApply', 'GAME', ['done' => 17]],
        17 => ['SetupFinish', 'GAME', ['start' => 40]],
        20 => ['RoundStart', 'GAME', ['plan' => 21]],
        21 => ['PlanningCollect', 'GAME', ['plot' => 22, 'window' => 30]],
        22 => ['PlanningPlot', 'MULTIPLE_ACTIVE_PLAYER', ['allConfirmed' => 23]],
        23 => ['PlanningReveal', 'GAME', ['window' => 30, 'next' => 40]],
        30 => ['EffectWindowOpen', 'GAME', ['choose' => 31, 'barrier' => 32]],
        31 => ['EffectWindowResolve', 'MULTIPLE_ACTIVE_PLAYER', ['allDone' => 32]],
        32 => ['EffectWindowBarrier', 'GAME', ['again' => 31]],
        40 => ['ActionPhaseRouter', 'GAME', [
            'budget' => 50, 'deploy' => 60, 'launch' => 70, 'link' => 80, 'proposal' => 90, 'orbit' => 100, 'round' => 20,
        ]],
        50 => ['BudgetDriver', 'GAME', ['window' => 30, 'next' => 40]],
        60 => ['DeployDriver', 'GAME', ['main' => 61, 'bonus' => 62, 'window' => 30, 'next' => 40]],
        61 => ['DeployMainChoose', 'MULTIPLE_ACTIVE_PLAYER', ['allConfirmed' => 60]],
        62 => ['DeployBonusChoose', 'MULTIPLE_ACTIVE_PLAYER', ['allConfirmed' => 60]],
        70 => ['LaunchDriver', 'GAME', ['main' => 71, 'bonus' => 72, 'window' => 30, 'next' => 40]],
        71 => ['LaunchMainChoose', 'MULTIPLE_ACTIVE_PLAYER', ['allConfirmed' => 70]],
        72 => ['LaunchBonusChoose', 'MULTIPLE_ACTIVE_PLAYER', ['allConfirmed' => 70]],
        80 => ['LinkDriver', 'GAME', ['main' => 81, 'bonus' => 82, 'window' => 30, 'next' => 40]],
        81 => ['LinkMainChoose', 'MULTIPLE_ACTIVE_PLAYER', ['allConfirmed' => 80]],
        82 => ['LinkBonusChoose', 'MULTIPLE_ACTIVE_PLAYER', ['allConfirmed' => 80]],
        90 => ['ProposalDriver', 'GAME', ['choose' => 91, 'window' => 30, 'next' => 40]],
        91 => ['ProposalKeepChoose', 'MULTIPLE_ACTIVE_PLAYER', ['allConfirmed' => 90]],
        100 => ['OrbitDriver', 'GAME', ['window' => 30, 'next' => 110]],
        110 => ['EndCheck', 'GAME', ['end' => 120, 'nextRound' => 20]],
        120 => ['EndHandCashOut', 'GAME', ['scoring' => 121]],
        121 => ['FinalScoring', 'GAME', []],
    ];

    public function testThirtyStatesMatchTheSpec(): void
    {
        $graph = StateGraph::load();
        self::assertCount(30, $graph);
        foreach (self::EXPECTED as $id => [$class, $type, $transitions]) {
            self::assertArrayHasKey($id, $graph, "state $id ($class)");
            self::assertSame($class, $graph[$id]['class']);
            self::assertSame($type, $graph[$id]['type'], "type of $class");
            self::assertEquals($transitions, $graph[$id]['transitions'], "transitions of $class");
        }
    }

    public function testIdsAreUniqueAndAvoidReservedOnes(): void
    {
        $ids = array_keys(StateGraph::load());
        self::assertSame($ids, array_values(array_unique($ids)));
        self::assertNotContains(1, $ids);
        self::assertNotContains(99, $ids);
        self::assertSame(array_keys(self::EXPECTED), $ids);
    }

    public function testStateIdConstantsAreTheStateList(): void
    {
        $constants = array_values((new \ReflectionClass(StateId::class))->getConstants());
        $ids = array_keys(StateGraph::load());
        sort($constants);
        self::assertSame($ids, $constants);
    }

    public function testEveryTransitionTargetExists(): void
    {
        $graph = StateGraph::load();
        foreach ($graph as $id => $state) {
            foreach ($state['transitions'] as $name => $target) {
                self::assertArrayHasKey($target, $graph, "$id $name");
            }
        }
    }

    public function testEveryStateIsReachableFromTheInitialState(): void
    {
        $graph = StateGraph::load();
        $seen = [];
        $queue = [StateId::SETUP_LAUNCH_PAD_NEXT];
        while ($queue !== []) {
            $id = array_pop($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            foreach ($graph[$id]['transitions'] as $target) {
                $queue[] = $target;
            }
        }
        $reached = array_keys($seen);
        sort($reached);
        self::assertSame(array_keys($graph), $reached);
    }
}
