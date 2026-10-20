<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Effects\EffectEngine;
use Bga\Games\projectedgeworth\Effects\Question\Answer;
use Bga\Games\projectedgeworth\Effects\Question\Question;
use Bga\Games\projectedgeworth\Effects\Runtime\EffectRuntime;
use Bga\Games\projectedgeworth\Flow\Action\LinkCommitter;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Action\StagedLinkConverter;

/**
 * 効果の窓の状態 30〜32 の処理（009 §3.5、§6）。効果エンジン（`EffectRuntime`）が結線されていなければ、窓は効果なしで素通りする。
 *
 * - 30 `open()`: 窓のイベントで `EffectEngine::openWindow()`。窓を開いた時点の公開情報を `window_snapshot` に保存する（010 §9.4）。
 *   選択が要る人がいれば 'choose'（31）、いなければ 'barrier'（32）。
 * - 31 `playersNeedingInput()`・`resolveArgs()`・`use()`・`skip()`・`order()`・`zombie()`・`sell()`: 全員並行。各操作のあとに窓の状態を DB へ保存する
 *   （再読み込みに耐える）。他人に見える結果は `held_notif` に積み、本人にだけ先に送る（010 §10.5）。
 * - 32 `barrier()`: ステージされた通信確立を全員分コミット（`LinkCommitter`）→ `LINKED` を `collect` → もう一度バリア、を繰り返す。
 *   選択が要る人が出たら 'again'（31 へ）、なければ窓を閉じて保留通知を流し、`window_resume` へ。
 */
final class WindowFlow
{
    public function __construct(private readonly FlowContext $ctx)
    {
    }

    private function runtime(): ?EffectRuntime
    {
        return $this->ctx->effects;
    }

    private function engine(): EffectEngine
    {
        return ($this->runtime() ?? throw new \LogicException('no effect runtime'))->engine();
    }

    private function notices(): EffectNotices
    {
        return new EffectNotices($this->ctx);
    }

    // ---- 30 ----

    /**
     * 30: 窓を開く。
     */
    public function open(): DriverResult
    {
        $rt = $this->runtime();
        if ($rt === null) {
            return new DriverResult('barrier');
        }
        $globals = $this->ctx->globals;
        $windowId = $globals->windowId() ?? throw new \LogicException('no window id');
        $events = $rt->events->take($windowId);
        $globals->setWindowGen(0);
        $globals->setWindowEffects(0);
        $globals->setWindowSnapshot((new PublicView($this->ctx))->snapshotData());

        $engine = $this->engine();
        $mark = $rt->game->journalMark();
        $report = $engine->openWindow($windowId, $events);
        if ($report->stagedLinks !== []) {
            $engine->restage($report->stagedLinks);   // コミットは 32 で全員分を一斉に行う
        }
        $notices = $this->flush(0, $mark);

        return new DriverResult($report->needsInput() ? 'choose' : 'barrier', $notices);
    }

    // ---- 31 ----

    /**
     * 31 の参加者: 選択が要る効果のあるプレイヤー（席順）。
     *
     * @return list<int>
     */
    public function playersNeedingInput(): array
    {
        return $this->runtime() === null ? [] : $this->engine()->playersNeedingInput();
    }

    /**
     * 31 の args（010 §13）。公開部分は窓の種類と待っている人（効果を持つカードは公開情報）、`_private` は選択が要る本人の問い。
     *
     * @return array<string, mixed>
     */
    public function resolveArgs(): array
    {
        $args = [
            'windowId' => $this->ctx->globals->windowId(),
            'waiting' => [],
            'round' => $this->ctx->globals->roundNo(),
        ];
        if ($this->runtime() === null) {
            return $args;
        }
        $engine = $this->engine();
        $services = $this->ctx->services;
        $private = [];
        foreach ($engine->playersNeedingInput() as $playerId) {
            $pending = $engine->pendingFor($playerId);
            $args['waiting'][] = [
                'player_id' => $playerId,
                'sources' => array_values(array_unique(array_column($pending, 'cardKey'))),
            ];
            $private[$playerId] = [
                'windowId' => $args['windowId'],
                'pending' => $pending,
                'questions' => array_map(static fn(Question $q): array => $q->toArray(), $engine->questionsFor($playerId)),
                'hand' => $services->cards->idsIn(CardLocation::Hand, $playerId),
                'sellValue' => $services->selling->price($playerId),
            ];
        }
        $args['_private'] = $private;

        return $args;
    }

    /**
     * 答えた後に送る、窓の進み具合の通知（N34・N35）。状態 31 の args は入場時のものなので、本人には残りの問いを、全員には待機表示を送る。
     * 答えたプレイヤーが済んだ（問いが残っていない）ときは、本人の問いは空で送る。
     *
     * @return list<Notice>
     */
    public function progressNotices(int $playerId): array
    {
        $args = $this->resolveArgs();
        /** @var array<int, array<string, mixed>> $private */
        $private = $args['_private'] ?? [];
        $mine = $private[$playerId] ?? [
            'windowId' => $args['windowId'],
            'pending' => [],
            'questions' => [],
            'hand' => [],
            'sellValue' => 0,
        ];
        /** @var list<array{player_id: int, sources: list<string>}> $waiting */
        $waiting = $args['waiting'];

        return [$this->ctx->notices->effectQuestions($playerId, $mine), $this->ctx->notices->effectWaiting($waiting)];
    }

    /**
     * 待機表示だけの通知（N35）。zombie が済んだときなど、本人に問いを送る相手がいないとき。
     *
     * @return list<Notice>
     */
    public function waitingNotices(): array
    {
        /** @var list<array{player_id: int, sources: list<string>}> $waiting */
        $waiting = $this->resolveArgs()['waiting'];

        return [$this->ctx->notices->effectWaiting($waiting)];
    }

    /**
     * `actUseEffect`: [Use]。取り消せない。
     *
     * @param array<string, mixed> $fields 問いのフィールドの key => 値
     * @param list<int> $sell 売却するカード ID
     * @return list<Notice>
     * @throws RuleViolation
     */
    public function use(int $playerId, int $entryId, array $fields, array $sell): array
    {
        return $this->act($playerId, fn(EffectEngine $e) => $e->useEffect($playerId, $entryId, new Answer($fields, $sell)));
    }

    /**
     * `actSkipEffect`: [Skip]（N28）。
     *
     * @return list<Notice>
     * @throws RuleViolation
     */
    public function skip(int $playerId, int $entryId): array
    {
        $item = null;
        foreach ($this->engine()->pendingFor($playerId) as $row) {
            if ($row['entryId'] === $entryId) {
                $item = $row;
            }
        }
        $notices = $this->act($playerId, fn(EffectEngine $e) => $e->skipEffect($playerId, $entryId));
        if ($item !== null) {
            $hostKey = $this->hostKeyOf($playerId, $item['cardKey']);
            $held = $this->notices();
            $early = $held->hold($playerId, [$held->skipped($playerId, $item['cardKey'], $hostKey)]);
            $notices = [...$early, ...$notices];
        }

        return $notices;
    }

    /**
     * `actOrderEffects`: 順序を確定する。
     *
     * @param list<int> $entryIds
     * @return list<Notice>
     * @throws RuleViolation
     */
    public function order(int $playerId, array $entryIds): array
    {
        return $this->act($playerId, fn(EffectEngine $e) => $e->orderEffects($playerId, $entryIds));
    }

    /**
     * zombie: プールが空になるまで既定の答えを繰り返す（009 §9.2）。
     *
     * @return list<Notice>
     */
    public function zombie(int $playerId): array
    {
        if ($this->runtime() === null) {
            return [];
        }
        $rt = $this->runtime();
        $mark = $rt->game->journalMark();
        $this->engine()->zombie($playerId);

        return $this->flush($playerId, $mark);
    }

    /**
     * 窓の中の売却（支払いのため。009 §8）。他人に見える結果は保留する。
     *
     * @param list<int> $cardIds
     * @return list<Notice>
     * @throws RuleViolation
     */
    public function sell(int $playerId, array $cardIds): array
    {
        if (!in_array($playerId, $this->playersNeedingInput(), true)) {
            throw new RuleViolation('You cannot sell cards now');
        }
        $services = $this->ctx->services;
        $n = $this->ctx->notices;
        $mark = $this->ctx->reshuffleMark();
        $gain = $services->selling->sell($playerId, $cardIds, []);
        $held = [$n->cardsDiscarded($playerId, $cardIds, 'sell', $this->ctx->discardCount())];
        array_push($held, ...array_filter([$n->mayaChanged($playerId, $gain, $services->players->maya($playerId), 'sell')]));
        if ($this->ctx->reshuffleNotices($mark) !== []) {
            array_unshift($held, ...$this->ctx->reshuffleNotices($mark));
        }

        return $this->notices()->hold($playerId, $held);
    }

    /**
     * @param \Closure(EffectEngine): void $operation
     * @return list<Notice>
     * @throws RuleViolation
     */
    private function act(int $playerId, \Closure $operation): array
    {
        $rt = $this->runtime() ?? throw new RuleViolation('Card effects are not available');
        if (!in_array($playerId, $this->engine()->playersNeedingInput(), true)) {
            throw new RuleViolation('You have no effect to resolve');
        }
        $mark = $rt->game->journalMark();
        try {
            $operation($this->engine());
        } catch (\Bga\Games\projectedgeworth\Effects\EffectViolation $violation) {
            throw new RuleViolation($violation->getMessage());
        }

        return $this->flush($playerId, $mark);
    }

    private function hostKeyOf(int $playerId, string $cardKey): string
    {
        foreach ($this->runtime()?->game->hosts($playerId) ?? [] as $host) {
            if ($host->cardKey === $cardKey) {
                return $host->key();
            }
        }

        return 'P:0';
    }

    // ---- 32 ----

    /**
     * 32: バリア。ステージされた通信確立をコミットし、選択が要る人がいれば 'again'、いなければ窓を閉じる。
     */
    public function barrier(): WindowExit
    {
        $rt = $this->runtime();
        if ($rt === null) {
            return $this->close();
        }
        $engine = $this->engine();
        $services = $this->ctx->services;
        $notices = [];
        $committer = new LinkCommitter($services);
        for ($guard = 0; $guard < 100; $guard++) {
            $mark = $rt->game->journalMark();
            $report = $engine->barrier();
            if ($report->stagedLinks === []) {
                $notices = [...$notices, ...$this->flush(0, $mark)];
                break;
            }
            $generation = $this->ctx->globals->incWindowGen();
            $before = [];
            foreach ($services->players->playerIds() as $playerId) {
                $before[$playerId] = $services->players->maya($playerId);
            }
            $commit = $committer->commit(StagedLinkConverter::requests($report->stagedLinks));
            foreach (StagedLinkConverter::events($commit, $generation) as $event) {
                $engine->collect($event);
            }
            $notices = [...$notices, ...$this->flush(0, $mark)];
            // 保留（ここまでの窓の結果）を先に流し、コミットの結果を続ける
            $notices = [...$notices, ...$this->notices()->release(), ...(new OutcomeNotices($this->ctx))->barrierCommit($commit, $before)];
        }
        if (!$report->canClose()) {
            return new WindowExit(null, $notices);
        }

        $engine->closeWindow();
        $rt->store->clear();
        $windowId = (string) $this->ctx->globals->windowId();
        $notices = [...$notices, ...$this->notices()->release(), $this->notices()->closed($windowId)];
        $exit = $this->close();

        return new WindowExit($exit->resume, [...$notices, ...$this->ctx->sync(), ...$exit->notices]);
    }

    /**
     * 窓を閉じて、戻り先（window_resume）へ。
     */
    public function close(): WindowExit
    {
        $globals = $this->ctx->globals;
        $resume = $globals->windowResume();
        $windowId = $globals->windowId();
        if ($resume === null || $windowId === null) {
            throw new \LogicException('the effect window has no return state');
        }
        $globals->setWindowId(null);
        $globals->setWindowResume(null);
        $globals->setWindowGen(0);
        $globals->setWindowEffects(0);
        $globals->setWindowSnapshot(null);
        $notices = $this->runtime() === null ? [$this->ctx->notices->windowClosed($windowId, [])] : [];

        return new WindowExit($resume, $notices);
    }

    // ---- 共通 ----

    /**
     * 操作のあとの後始末: ログと書き込みの記録から通知を作り、窓の状態を保存する。保留を積み、本人への先送りと本人だけの通知を返す。
     *
     * @return list<Notice>
     */
    private function flush(int $origin, int $mark): array
    {
        $rt = $this->runtime() ?? throw new \LogicException('no effect runtime');
        $logs = $this->engine()->takeLogs();
        $journal = $rt->game->journalSince($mark);
        $rt->save();
        $effectNotices = $this->notices();
        $built = $effectNotices->build($origin, $logs, $journal);
        $early = $effectNotices->hold($origin, $built['held']);

        return [...$built['private'], ...$early];
    }
}
