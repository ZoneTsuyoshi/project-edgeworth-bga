<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Machine;

use Bga\Games\projectedgeworth\Flow\Machine\FinalScoreOutcome;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Stats\StatsRecorder;
use ProjectEdgeworthTests\Support\ArrayStatStore;

/** 通しで終了まで進めた通知から統計を導き、名声の統計が「Final situation」の内訳（fame_detail）と一致すること（010 §14）。 */
final class StatsTest extends MachineTestCase
{
    public function testFameStatsMatchTheScoringBreakdown(): void
    {
        $this->startGame();
        $waiting = $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, [], [StateId::PLANNING_PLOT]);
        $pad = $this->playerValues->padCell(102);
        $placements = [];
        $cell = 0;
        while (count($placements) < 16) {
            if ($cell !== $pad && !in_array($cell, $this->playerValues->padCells(), true)) {
                $placements[] = ['playerId' => 102, 'cell' => $cell];
            }
            $cell++;
        }
        $this->machine->ctx->services->links->commit($placements, 1, 1);
        self::assertSame(0, $this->walker->play($waiting));

        $store = new ArrayStatStore();
        $stats = new StatsRecorder($store);
        $stats->init();
        $stats->recordNotices($this->walker->notices);
        // 状態 121 の処理（名声の計算は何度呼んでも同じ）
        $outcome = $this->machine->end()->score();
        $stats->recordFinal($outcome);

        foreach ([101, 102] as $playerId) {
            $detail = $this->playerValues->fameDetail($playerId);
            self::assertNotNull($detail);
            $sum = 0;
            foreach (StatsRecorder::FAME_SECTIONS as $section => $stat) {
                self::assertSame($detail[$section]['points'], $store->player($stat, $playerId), "$stat ($section)");
                $sum += $detail[$section]['points'];
            }
            self::assertSame($detail['total'], $sum);
            self::assertSame($detail['total'], $store->player('fame_total', $playerId));
            self::assertSame($detail['I']['budget'], $store->player('budget_final', $playerId));
            self::assertSame($this->playerValues->budget($playerId), $store->player('budget_final', $playerId));
            self::assertSame(count($detail['VI']['sats']), $store->player('active_satellites_final', $playerId));
        }
        self::assertSame(3, $store->player('fame_end', 102));
        self::assertSame(0, $store->player('fame_end', 101));
        self::assertSame(0, $store->player('cubes_left_final', 102));
        self::assertSame(16, $store->player('cubes_left_final', 101));

        // ゲーム中の通知から導く統計
        self::assertSame($this->globals->roundNo(), $store->table['rounds_played']);
        self::assertSame(StatsRecorder::END_REASON_LINKS, $store->table['end_reason']);
        self::assertGreaterThan(0, $store->player('actions_chosen_1', 101) + $store->player('actions_chosen_5', 101));
        self::assertGreaterThan(0, $store->player('maya_earned', 101), 'I 予算発行のマヤを数える');
        $revealed = count($this->walker->noticesNamed('actionRevealed'));
        $chosen = 0;
        foreach ([101, 102] as $playerId) {
            for ($i = 1; $i <= 5; $i++) {
                $chosen += (int) $store->player("actions_chosen_$i", $playerId);
            }
        }
        self::assertSame($revealed, $chosen);
        self::assertInstanceOf(FinalScoreOutcome::class, $outcome);
    }
}
