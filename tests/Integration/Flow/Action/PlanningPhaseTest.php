<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Action;

use Bga\Games\projectedgeworth\Data\ActionCardState;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;

final class PlanningPhaseTest extends ActionTestCase
{
    private const PLOT_STATE = 22;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startGame(3);
    }

    /** @param array<int, int> $plots プレイヤー ID => アクション番号 */
    private function playRound(array $plots): void
    {
        $planning = $this->phases->planning();
        $planning->collect();
        $runner = $this->phases->runner();
        $runner->enter($planning, self::PLOT_STATE);
        foreach ($plots as $playerId => $action) {
            $runner->confirm($planning, self::PLOT_STATE, $playerId, ['action' => $action]);
        }
        $runner->resolve($planning, self::PLOT_STATE);
    }

    public function testFirstRoundOffersAllFiveActions(): void
    {
        $this->phases->planning()->collect();
        foreach ($this->ids as $id) {
            $this->assertSame([1, 2, 3, 4, 5], $this->phases->planning()->selectable($id));
        }
    }

    public function testRevealDecidesPhasesToRunAndBonusHolders(): void
    {
        $this->playRound([101 => 3, 102 => 1, 103 => 3]);

        $this->assertSame([1, 3], $this->phases->planning()->phasesToRun());
        $this->assertSame([1 => [102], 3 => [101, 103]], $this->globals->bonusHolders());
        $this->assertSame(1, $this->phases->planning()->nextPhase(null));
        $this->assertSame(3, $this->phases->planning()->nextPhase(1));
        $this->assertNull($this->phases->planning()->nextPhase(3));
        $actions = $this->phases->services()->actionCards;
        $this->assertSame(ActionCardState::Upright, $actions->state(101, 3));
        $this->assertSame(ActionCardState::Hand, $actions->state(101, 1));
    }

    public function testRetrievalFollowsSection61AcrossThreeRounds(): void
    {
        // 第 1 ラウンド: 101 は II、102 は I を選ぶ
        $this->playRound([101 => 2, 102 => 1, 103 => 4]);

        // 第 2 ラウンド: II は横向きで選べない。I は手札に戻っていて、連続して選べる
        $planning = $this->phases->planning();
        $planning->collect();
        $this->assertSame([1, 3, 4, 5], $planning->selectable(101));
        $this->assertSame([1, 2, 3, 4, 5], $planning->selectable(102));
        $this->assertSame([1, 2, 3, 5], $planning->selectable(103));
        $actions = $this->phases->services()->actionCards;
        $this->assertSame(ActionCardState::Sideways, $actions->state(101, 2));

        $runner = $this->phases->runner();
        $runner->enter($planning, self::PLOT_STATE);
        $runner->confirm($planning, self::PLOT_STATE, 101, ['action' => 3]);
        $runner->confirm($planning, self::PLOT_STATE, 102, ['action' => 1]);
        $runner->confirm($planning, self::PLOT_STATE, 103, ['action' => 1]);
        $runner->resolve($planning, self::PLOT_STATE);

        // 第 3 ラウンド: 横向きだった II が手札に戻り、第 2 ラウンドの III は横向きになる
        $planning->collect();
        $this->assertSame([1, 2, 4, 5], $planning->selectable(101));
        $this->assertSame([1, 2, 3, 4, 5], $planning->selectable(102));
        $this->assertSame([1, 2, 3, 4, 5], $planning->selectable(103));
    }

    public function testSidewaysCardCannotBePlotted(): void
    {
        $this->playRound([101 => 2, 102 => 1, 103 => 1]);
        $this->phases->planning()->collect();
        $this->expectException(RuleViolation::class);
        $this->phases->planning()->validate(101, StepMode::Main, ['action' => 2]);
    }

    public function testInvalidActionNumbersAreRejected(): void
    {
        $this->phases->planning()->collect();
        foreach ([['action' => 0], ['action' => 6], ['action' => 'x'], []] as $data) {
            try {
                $this->phases->planning()->validate(101, StepMode::Main, $data);
                $this->fail('expected RuleViolation');
            } catch (RuleViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testChoiceCanBeChangedUntilEveryoneIsConfirmedThenResolves(): void
    {
        $planning = $this->phases->planning();
        $planning->collect();
        $runner = $this->phases->runner();
        $entry = $runner->enter($planning, self::PLOT_STATE);
        $this->assertSame([101, 102, 103], $entry->active);

        $this->assertFalse($runner->confirm($planning, self::PLOT_STATE, 101, ['action' => 2]));
        $this->assertFalse($runner->confirm($planning, self::PLOT_STATE, 101, ['action' => 5]));
        $this->assertFalse($runner->confirm($planning, self::PLOT_STATE, 102, ['action' => 4]));
        $this->assertTrue($runner->confirm($planning, self::PLOT_STATE, 103, ['action' => 4]));

        $result = $runner->resolve($planning, self::PLOT_STATE);
        $this->assertSame(5, $result->outcomeOf(101)?->details['action']);
        $this->assertSame([4 => [102, 103], 5 => [101]], $this->globals->bonusHolders());
        $this->assertSame([], $this->phases->services()->choices->confirmedPlayers(self::PLOT_STATE));
    }

    public function testZombieAndMissingChoicesDefaultToBudget(): void
    {
        $planning = $this->phases->planning();
        $planning->collect();
        $runner = $this->phases->runner();
        $runner->enter($planning, self::PLOT_STATE);
        $runner->confirm($planning, self::PLOT_STATE, 101, ['action' => 3]);
        $this->assertFalse($runner->zombie($planning, self::PLOT_STATE, 102));
        // 103 は未確定のまま resolve しても I として処理される
        $runner->resolve($planning, self::PLOT_STATE);
        $this->assertSame([1 => [102, 103], 3 => [101]], $this->globals->bonusHolders());
    }

    public function testNonParticipantCannotConfirm(): void
    {
        $planning = $this->phases->planning();
        $planning->collect();
        $this->phases->runner()->enter($planning, self::PLOT_STATE);
        $this->expectException(RuleViolation::class);
        $this->phases->runner()->confirm($planning, self::PLOT_STATE, 999, ['action' => 1]);
    }
}
