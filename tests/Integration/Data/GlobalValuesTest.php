<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Data;

use Bga\Games\ProjectEdgeworth\Board\BoardFace;
use Bga\Games\ProjectEdgeworth\Data\FlowPhase;
use Bga\Games\ProjectEdgeworth\Data\GlobalValues;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\ArrayGlobals;

/** DB を使わないが、データ層の一部としてここに置く（結合テストの一式で実行する）。 */
final class GlobalValuesTest extends TestCase
{
    private ArrayGlobals $raw;
    private GlobalValues $globals;

    protected function setUp(): void
    {
        $this->raw = new ArrayGlobals();
        $this->globals = new GlobalValues($this->raw);
    }

    public function testDefaultsBeforeInitialize(): void
    {
        $this->assertSame(0, $this->globals->roundNo());
        $this->assertSame(FlowPhase::Setup, $this->globals->flowPhase());
        $this->assertNull($this->globals->windowId());
        $this->assertNull($this->globals->windowSnapshot());
        $this->assertSame([], $this->globals->bonusHolders());
        $this->assertSame(BoardFace::STANDARD, $this->globals->boardFace());
    }

    public function testInitializeWritesEveryInternalKey(): void
    {
        $this->globals->initialize();
        foreach (
            ['round_no', 'flow_phase', 'flow_step', 'flow_ctx', 'window_id', 'window_resume', 'window_gen', 'window_effects',
                'window_snapshot', 'commit_seq', 'held_seq', 'bonus_holders', 'step_participants', 'end_condition_players',
                'progress_high_water'] as $key
        ) {
            $this->assertTrue($this->raw->has($key), $key);
        }
    }

    public function testRoundTripsOfTypedValues(): void
    {
        $this->globals->setFlowPhase(FlowPhase::LaunchTurn);
        $this->globals->setWindowId('DE');
        $this->globals->setWindowResume(61);
        $this->globals->setStepParticipants([12, 10]);
        $this->globals->setBonusHolders([2 => [10], 4 => [11, 12]]);
        $this->globals->setRewardCards(['T001', 'T013']);
        $this->globals->setBoardFace(BoardFace::ADVANCED);
        $this->globals->setBoardCols(9);
        $this->globals->setRowShift(1);
        $this->globals->setEndConditionPlayers([10, 11]);
        $this->globals->setFlowContext(['a' => 1]);
        $this->globals->setWindowSnapshot(['players' => [10 => ['maya' => 3]]]);

        $this->assertSame(FlowPhase::LaunchTurn, $this->globals->flowPhase());
        $this->assertSame('DE', $this->globals->windowId());
        $this->assertSame(61, $this->globals->windowResume());
        $this->assertSame([12, 10], $this->globals->stepParticipants());
        $this->assertSame([2 => [10], 4 => [11, 12]], $this->globals->bonusHolders());
        $this->assertSame([2, 4], $this->globals->selectedActions());
        $this->assertSame(['T001', 'T013'], $this->globals->rewardCards());
        $this->assertSame(BoardFace::ADVANCED, $this->globals->boardFace());
        $this->assertSame(9, $this->globals->boardCols());
        $this->assertSame(1, $this->globals->rowShift());
        $this->assertSame([10, 11], $this->globals->endConditionPlayers());
        $this->assertSame(['a' => 1], $this->globals->flowContext());
        $this->assertSame(['players' => [10 => ['maya' => 3]]], $this->globals->windowSnapshot());

        $this->globals->setWindowId(null);
        $this->globals->setWindowSnapshot(null);
        $this->globals->setBonusHolders([]);
        $this->globals->setFlowContext([]);
        $this->assertNull($this->globals->windowId());
        $this->assertNull($this->globals->windowSnapshot());
        $this->assertSame([], $this->globals->bonusHolders());
        $this->assertSame([], $this->globals->flowContext());
    }

    public function testCountersIncrease(): void
    {
        $this->globals->initialize();
        $this->assertSame(1, $this->globals->incCommitSeq());
        $this->assertSame(2, $this->globals->incCommitSeq());
        $this->assertSame(1, $this->globals->incHeldSeq());
        $this->assertSame(1, $this->globals->incWindowGen());
        $this->assertSame(1, $this->globals->incWindowEffects());
        $this->assertSame(2, $this->globals->commitSeq());
    }

    public function testRowShiftMustBeZeroOrOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->globals->setRowShift(2);
    }
}
