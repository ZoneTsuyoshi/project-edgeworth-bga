<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Runtime;

use Bga\Games\projectedgeworth\Data\FlowPhase;
use Bga\Games\projectedgeworth\Data\GlobalValues;
use Bga\Games\projectedgeworth\Effects\Bonus\BonusDeclaration;
use Bga\Games\projectedgeworth\Effects\Context\GameEvent;
use Bga\Games\projectedgeworth\Material\ActionKind;
use Bga\Games\projectedgeworth\Material\Hook;

/**
 * 窓 1 つに渡すイベントの一覧（011 §3-1）。ドライバが記録した分（`EventLedger`）に、状態から決まるイベント
 * （`B0`・`XD`・`B2`・`B3`・`PB`・`O0`）を足す。
 *
 * `ACTION_BONUS_STEP`（R012・R013）は 007 の窓に記号が無いので、ボーナスのコミットの後に内部の記号 `AB` の窓を開いて扱う
 * （[Use] / [Skip] を聞くため。判断として報告済み）。
 */
final class WindowEvents
{
    public const ACTION_BONUS_WINDOW = 'AB';

    public function __construct(private readonly GlobalValues $globals, private readonly EventLedger $ledger)
    {
    }

    /**
     * @return list<GameEvent>
     */
    public function peek(string $windowId): array
    {
        return [...$this->synthesized($windowId), ...$this->ledger->peek($windowId)];
    }

    /**
     * @return list<GameEvent>
     */
    public function take(string $windowId): array
    {
        $events = $this->peek($windowId);
        $this->ledger->discard($windowId);

        return $events;
    }

    public function discard(string $windowId): void
    {
        $this->ledger->discard($windowId);
    }

    /**
     * @return list<GameEvent>
     */
    private function synthesized(string $windowId): array
    {
        $holders = $this->globals->bonusHolders();

        return match ($windowId) {
            'B0' => [new GameEvent(Hook::BudgetStart, null)],
            'B2' => [new GameEvent(Hook::BudgetBonusGain, null, data: ['holders' => $holders[1] ?? []])],
            'B3' => [new GameEvent(Hook::BudgetFree, null)],
            'PB' => [new GameEvent(Hook::ProposalAfter, null)],
            'O0' => [new GameEvent(Hook::OrbitStart, null)],
            'XD' => $this->declaration($holders),
            default => [],
        };
    }

    /**
     * @param array<int, list<int>> $holders
     * @return list<GameEvent>
     */
    private function declaration(array $holders): array
    {
        $no = match ($this->globals->flowPhase()) {
            FlowPhase::Budget => 1,
            FlowPhase::Deploy => 2,
            FlowPhase::LaunchTurn => 3,
            FlowPhase::Link => 4,
            FlowPhase::Proposal => 5,
            default => null,
        };
        if ($no === null) {
            return [];
        }
        $action = [1 => ActionKind::Budget, 2 => ActionKind::Deploy, 3 => ActionKind::LaunchTurn, 4 => ActionKind::Link, 5 => ActionKind::Proposal][$no];

        return [BonusDeclaration::event($action, $holders[$no] ?? [])];
    }
}
