<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support;

use Bga\Games\projectedgeworth\Flow\Machine\DriverResult;
use Bga\Games\projectedgeworth\Flow\Machine\Machine;
use Bga\Games\projectedgeworth\Flow\Machine\Notice;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Setup\StartingHand;

/**
 * 状態クラスを使わずに、状態機械を通しで回す。States/ のソースから読んだ遷移（StateGraph）にしたがい、各状態でやる処理は
 * Machine を直接呼ぶ（状態クラスがやるのと同じ呼び出し）。人の入力待ちの状態（11 と同時選択の状態）で止まる。
 */
final class StateWalker
{
    /** @var list<Notice> */
    public array $notices = [];
    /** @var list<int> 通った状態（順） */
    public array $visited = [];
    /** @var list<int> 入場前の getArgs が入場後と食い違った同時選択の状態（BGA は getArgs を onEnteringState の前に呼ぶ） */
    public array $staleArgsStates = [];
    /** @var list<int> 入力待ちの同時選択の状態でアクティブなプレイヤー */
    public array $active = [];
    /** @var array<int, array{class: string, type: string, transitions: array<string, int>}> */
    private array $graph;
    private int $steps = 0;

    public function __construct(
        private readonly Machine $machine,
        private readonly StartingHand $hand = StartingHand::None,
    ) {
        $this->graph = StateGraph::load();
    }

    /**
     * 状態に入り、人の入力待ち（または終了）になるまで進める。
     *
     * @return int 入力待ちの状態 ID。ゲームが終わったら 0
     */
    public function run(int $stateId): int
    {
        while (true) {
            if (++$this->steps > 5000) {
                throw new \LogicException("state machine does not stop (last state $stateId)");
            }
            $this->visited[] = $stateId;
            $result = $this->enter($stateId);
            if ($result === null) {
                return $stateId;
            }
            if ($result === 'END') {
                return 0;
            }
            $stateId = is_int($result) ? $result : $this->target($stateId, $result);
        }
    }

    /**
     * 入力待ちの状態を、遷移名で抜けて続きを進める。
     */
    public function resume(int $stateId, string $transition): int
    {
        return $this->run($this->target($stateId, $transition));
    }

    /**
     * 入力待ちが 11 SetupLaunchPadPlace か。
     */
    public function isLaunchPadPlace(int $stateId): bool
    {
        return $stateId === StateId::SETUP_LAUNCH_PAD_PLACE;
    }

    /**
     * 同時選択の状態か。
     */
    public function isChoice(int $stateId): bool
    {
        return ($this->graph[$stateId]['type'] ?? '') === 'MULTIPLE_ACTIVE_PLAYER';
    }

    /**
     * @param array<int, callable(int, int): (array<mixed>|null)> $choices 状態 ID => (プレイヤー ID, 状態 ID) => 確定内容。null なら zombie の既定
     * @param list<int> $stopAt これらの状態で入力待ちになったら止まって返す（入力はまだ受け付けない）
     * @return int 止まった状態 ID。ゲームが終わったら 0
     */
    public function autoPlay(int $stateId, array $choices = [], array $stopAt = []): int
    {
        return $this->play($this->run($stateId), $choices, $stopAt, true);
    }

    /**
     * 入力待ちの状態から、人の入力を自動で入れて進める。最初の入力待ちでは止まらない（続きから進めるため）。
     *
     * @param array<int, callable(int, int): (array<mixed>|null)> $choices
     * @param list<int> $stopAt
     */
    public function play(int $waiting, array $choices = [], array $stopAt = [], bool $checkFirst = false): int
    {
        $first = !$checkFirst;
        while ($waiting !== 0) {
            if (!$first && in_array($waiting, $stopAt, true)) {
                return $waiting;
            }
            $first = false;
            if ($waiting === StateId::EFFECT_WINDOW_RESOLVE) {
                $this->answerWindow($choices[StateId::EFFECT_WINDOW_RESOLVE] ?? null);
                $waiting = $this->resume($waiting, 'allDone');
                continue;
            }
            if ($this->isLaunchPadPlace($waiting)) {
                $placer = $this->machine->setup()->nextPlacer();
                $this->notices = array_merge($this->notices, $this->machine->setup()->zombiePlace((int) $placer));
                $waiting = $this->resume($waiting, 'placed');
                continue;
            }
            if (!$this->isChoice($waiting)) {
                throw new \LogicException("unexpected waiting state $waiting");
            }
            $flow = $this->machine->choice($waiting);
            foreach ($this->active as $playerId) {
                $data = isset($choices[$waiting]) ? $choices[$waiting]($playerId, $waiting) : null;
                $result = $data === null ? $flow->zombie($playerId) : $flow->confirm($playerId, $data);
                $this->notices = array_merge($this->notices, $result->notices);
            }
            $waiting = $this->resume($waiting, 'allConfirmed');
        }
        return 0;
    }

    /**
     * 31 の入力待ち: アクティブな各プレイヤーが、プールが空になるまで答える。
     * `$decide(playerId, privateArgs)` は ['use' => [entryId, fields, sell]] / ['skip' => entryId] / ['order' => [entryId, …]] を返す。null は zombie。
     *
     * @param (callable(int, array<string, mixed>): (array<string, mixed>|null))|null $decide
     */
    private function answerWindow(?callable $decide): void
    {
        $windows = $this->machine->windows();
        foreach ($this->active as $playerId) {
            for ($guard = 0; $guard < 100 && in_array($playerId, $windows->playersNeedingInput(), true); $guard++) {
                $private = $windows->resolveArgs()['_private'][$playerId];
                $decision = $decide === null ? null : $decide($playerId, $private);
                if ($decision === null) {
                    $notices = $windows->zombie($playerId);
                } elseif (isset($decision['use'])) {
                    $notices = $windows->use($playerId, $decision['use'][0], $decision['use'][1] ?? [], $decision['use'][2] ?? []);
                } elseif (isset($decision['skip'])) {
                    $notices = $windows->skip($playerId, $decision['skip']);
                } else {
                    $notices = $windows->order($playerId, $decision['order']);
                }
                $this->notices = array_merge($this->notices, $notices);
            }
        }
    }

    /**
     * @return list<string> 送られた通知の名前（順）
     */
    public function noticeNames(): array
    {
        return array_map(static fn(Notice $n): string => $n->name, $this->notices);
    }

    /**
     * @return list<Notice>
     */
    public function noticesNamed(string $name): array
    {
        return array_values(array_filter($this->notices, static fn(Notice $n): bool => $n->name === $name));
    }

    private function target(int $stateId, string $transition): int
    {
        $target = $this->graph[$stateId]['transitions'][$transition] ?? null;
        if ($target === null) {
            throw new \LogicException("state $stateId has no transition '$transition'");
        }
        return $target;
    }

    /**
     * 状態 1 つ分の入場処理。遷移名(string)・状態 ID(int, 窓の戻り先)を返す。入力待ちなら null、ゲーム終了なら 'END'。
     */
    private function enter(int $stateId): string|int|null
    {
        $m = $this->machine;
        switch ($stateId) {
            case StateId::SETUP_LAUNCH_PAD_NEXT:
                return $m->setup()->nextPlacer() === null ? 'done' : 'place';
            case StateId::SETUP_LAUNCH_PAD_PLACE:
                return null;
            case StateId::SETUP_HAND_ROUTE:
                return $m->setup()->route($this->hand);
            case StateId::SETUP_MULLIGAN_APPLY:
                return $this->driver($m->setup()->applyMulligan());
            case StateId::SETUP_FINISH:
                return $this->driver($m->setup()->finish());
            case StateId::ROUND_START:
                return $this->driver($m->rounds()->start());
            case StateId::PLANNING_COLLECT:
                return $this->driver($m->rounds()->collect());
            case StateId::PLANNING_REVEAL:
                return $this->driver($m->rounds()->reveal());
            case StateId::EFFECT_WINDOW_OPEN:
                return $this->driver($m->windows()->open());
            case StateId::EFFECT_WINDOW_RESOLVE:
                $this->active = $m->windows()->playersNeedingInput();
                return $this->active === [] ? 'allDone' : null;
            case StateId::EFFECT_WINDOW_BARRIER:
                $exit = $m->windows()->barrier();
                $this->notices = array_merge($this->notices, $exit->notices);
                return $exit->resume ?? 'again';
            case StateId::ACTION_PHASE_ROUTER:
                return $this->driver($m->rounds()->route());
            case StateId::BUDGET_DRIVER:
                return $this->driver($m->drivers()->budget());
            case StateId::DEPLOY_DRIVER:
                return $this->driver($m->drivers()->deploy());
            case StateId::LAUNCH_DRIVER:
                return $this->driver($m->drivers()->action(3));
            case StateId::LINK_DRIVER:
                return $this->driver($m->drivers()->action(4));
            case StateId::PROPOSAL_DRIVER:
                return $this->driver($m->drivers()->proposal());
            case StateId::ORBIT_DRIVER:
                return $this->driver($m->orbit()->run());
            case StateId::END_CHECK:
                return $this->driver($m->end()->check());
            case StateId::END_HAND_CASH_OUT:
                return $this->driver($m->end()->cashOut());
            case StateId::FINAL_SCORING:
                $outcome = $m->end()->score();
                $this->notices = array_merge($this->notices, $outcome->notices);
                return 'END';
            default:
                if (!$this->isChoice($stateId)) {
                    throw new \LogicException("unhandled state $stateId");
                }
                $argsBefore = $m->choice($stateId)->args();
                [$entry, $notices] = $m->choice($stateId)->enter();
                if ($argsBefore !== $m->choice($stateId)->args()) {
                    $this->staleArgsStates[] = $stateId;
                }
                $this->notices = array_merge($this->notices, $notices);
                $this->active = $entry->active;
                return $entry->active === [] ? 'allConfirmed' : null;
        }
    }

    private function driver(DriverResult $result): string
    {
        $this->notices = array_merge($this->notices, $result->notices);
        return $result->transition;
    }
}
