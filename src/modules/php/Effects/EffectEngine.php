<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects;

use Bga\Games\ProjectEdgeworth\Effects\Condition\ConditionEvaluator;
use Bga\Games\ProjectEdgeworth\Effects\Context\EffectContext;
use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameEvent;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameMutator;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameView;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartCall;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartRegistry;
use Bga\Games\ProjectEdgeworth\Effects\Parts\ResultPart;
use Bga\Games\ProjectEdgeworth\Effects\Pool\ConflictResolver;
use Bga\Games\ProjectEdgeworth\Effects\Pool\DefaultOrder;
use Bga\Games\ProjectEdgeworth\Effects\Pool\PlanKind;
use Bga\Games\ProjectEdgeworth\Effects\Pool\PoolItem;
use Bga\Games\ProjectEdgeworth\Effects\Pool\PoolItemStatus;
use Bga\Games\ProjectEdgeworth\Effects\Pool\ResolvePlan;
use Bga\Games\ProjectEdgeworth\Effects\Pool\TriggerCollector;
use Bga\Games\ProjectEdgeworth\Effects\Pool\WindowState;
use Bga\Games\ProjectEdgeworth\Effects\Question\Answer;
use Bga\Games\ProjectEdgeworth\Effects\Question\AnswerValidator;
use Bga\Games\ProjectEdgeworth\Effects\Question\Question;
use Bga\Games\ProjectEdgeworth\Material\CostKind;
use Bga\Games\ProjectEdgeworth\Material\EffectCatalog;
use Bga\Games\ProjectEdgeworth\Material\EffectKind;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;

/**
 * 効果エンジン: 窓の入口（011 §5-2）。状態 30〜32 は、問いを渡して答えを受けるだけで、プールの収集・衝突の判定・順序・解決は
 * すべてここが行う（009 §6.2）。BGA のクラスには依存しない。
 *
 * 呼び方（状態 30〜32 から）:
 *
 * - 30 `EffectWindowOpen`: `openWindow($windowId, $events)` → `WindowReport::needsInput()` なら 31、でなければ 32。
 * - 31 `EffectWindowResolve`: `getArgs` で `questionsFor($playerId)`、`actUseEffect` / `actSkipEffect` / `actOrderEffects` で
 *   `useEffect()` / `skipEffect()` / `orderEffects()`、`zombie` で `zombie()`。プールが空になったプレイヤーから非アクティブにする
 *   （`playersNeedingInput()` に含まれなくなる）。
 * - 32 `EffectWindowBarrier`: `barrier()` → `stagedLinks` があれば全員分を一斉にコミット（世代 +1）して、起きた `LINKED` を
 *   `collect()` し、もう一度 `barrier()`。`needsInput()` なら 31 へ戻る。`canClose()` になったら `closeWindow()` で
 *   ログを受け取り、通知にまとめて戻り先へ進む。
 *
 * 窓の状態は `snapshot()` / `restore()` で JSON にでき、再読み込み・再接続のあとも同じ窓を続けられる。
 */
final class EffectEngine
{
    /** 1 窓で解決する効果の数の上限（安全弁。007 §8-3、009 §3.5） */
    public const EFFECT_LIMIT = 200;

    private readonly EffectContext $ctx;
    private readonly TriggerCollector $collector;
    private readonly ConflictResolver $conflicts;
    private readonly AnswerValidator $validator;
    private ?WindowState $state = null;

    /** @var array<int, true> 新しい要素が加わり、まだ進めていないプレイヤー */
    private array $dirty = [];

    public function __construct(
        private readonly GameView $view,
        private readonly GameMutator $mutator,
        private readonly PartRegistry $parts,
        private readonly EffectCatalog $catalog,
        ?ConditionEvaluator $conditions = null,
    ) {
        $this->ctx = new EffectContext($view, $mutator);
        $this->collector = new TriggerCollector($view, $catalog, $conditions ?? new ConditionEvaluator());
        $this->conflicts = new ConflictResolver();
        $this->validator = new AnswerValidator();
    }

    // ---- 窓の開閉 ----

    /**
     * 30: 窓を開く。トリガーされた効果で全プレイヤーのプールを作り、自動で済む効果を解決して、問いが要る人を返す。
     *
     * @param list<GameEvent> $events
     */
    public function openWindow(string $windowId, array $events): WindowReport
    {
        $this->state = new WindowState($windowId);
        $this->dirty = [];
        foreach ($events as $event) {
            $this->collect($event);
        }
        $this->settleAll();

        return $this->report();
    }

    /**
     * 32: バリア。ステージされた通信確立を取り出し（コミットは呼び出し側）、自動で済む効果を進めて、問いが要る人を返す。
     */
    public function barrier(): WindowReport
    {
        $this->requireState();
        $this->settleAll();

        return $this->report();
    }

    /**
     * 窓を閉じる。誰かの答え待ち・ステージの未コミットが残っていれば例外（窓の強制終了後は残りを捨てているので閉じられる）。
     */
    public function closeWindow(): WindowResult
    {
        $state = $this->requireState();
        if ($state->hasStaged() || $this->playersNeedingInput() !== []) {
            throw new \LogicException('the window still has unresolved effects or staged links');
        }
        $result = new WindowResult($state->logs(), $state->countResolved(), $state->isAborted());
        $this->state = null;
        $this->dirty = [];

        return $result;
    }

    public function isOpen(): bool
    {
        return $this->state !== null;
    }

    public function windowId(): ?string
    {
        return $this->state?->windowId;
    }

    /**
     * 窓の中で起きたイベントをプールに積む（連鎖。007 §8-1）。バリアのコミットで起きた `LINKED` もこれで積む。
     */
    public function collect(GameEvent $event): void
    {
        $state = $this->requireState();
        if ($state->isAborted()) {
            return;
        }
        foreach ($this->collector->collect($state, $event) as $item) {
            $this->dirty[$item->playerId] = true;
        }
    }

    /**
     * @return array<string, mixed>|null 窓の状態（JSON にできる）。窓が開いていなければ null
     */
    public function snapshot(): ?array
    {
        return $this->state?->toArray();
    }

    /**
     * @param array<string, mixed>|null $snapshot
     */
    public function restore(?array $snapshot): void
    {
        $this->state = $snapshot === null ? null : WindowState::fromArray($snapshot);
        $this->dirty = [];
    }

    // ---- 31: 問いと答え ----

    /**
     * 答える必要のあるプレイヤー（席順）。
     *
     * @return list<int>
     */
    public function playersNeedingInput(): array
    {
        $this->requireState();

        return array_values(array_filter(
            $this->view->playerIds(),
            fn(int $p): bool => in_array($this->plan($p)->kind, [PlanKind::Order, PlanKind::Ask], true),
        ));
    }

    /**
     * 本人向けの表示用: このプレイヤーのプールの残り（011 §6-1 の `pending`）。
     *
     * @return list<array{entryId: int, cardKey: string}>
     */
    public function pendingFor(int $playerId): array
    {
        return array_map(
            static fn(PoolItem $i): array => ['entryId' => $i->id, 'cardKey' => $i->cardKey],
            $this->requireState()->pending($playerId),
        );
    }

    /**
     * 今答えられる問い（011 §6-1）。順序が未決定の衝突する集合があれば `ORDER`（集合ごと）、なければ `EFFECT`
     * （順序が決まった衝突する効果は先頭の 1 つ、衝突がなければ任意・選択ありの全部を並列で）。
     * 読み取りだけで状態を変えない（`getArgs` から呼べる）。
     *
     * @return list<Question>
     */
    public function questionsFor(int $playerId): array
    {
        $plan = $this->plan($playerId);
        if ($plan->kind === PlanKind::Order) {
            return array_map(fn(array $set): Question => $this->orderQuestion($set), $plan->sets);
        }
        if ($plan->kind === PlanKind::Ask) {
            return array_map(fn(PoolItem $i): Question => $this->effectQuestion($i), $plan->items);
        }

        return [];
    }

    /**
     * `actUseEffect`: [Use]。答えを検証し、売却・支払い・効果の解決を行う（取り消せない。009 未確定 1）。
     */
    public function useEffect(int $playerId, int $entryId, Answer $answer): void
    {
        $item = $this->askable($playerId, $entryId);
        [$spec, $host, $part, $call] = $this->bind($item) ?? throw new EffectViolation('the effect is no longer available');

        $fields = $part->buildFields($call);
        $result = $this->validator->validate($fields, $answer);
        if (!$result->ok) {
            throw new EffectViolation($result->message);
        }
        $result = $part->validate($call, $answer);
        if (!$result->ok) {
            throw new EffectViolation($result->message);
        }
        $this->checkSale($playerId, $spec, $answer, $item->stage === 1);

        $this->resolve($item, $answer);
        $this->settle($playerId);
    }

    /**
     * `actSkipEffect`: [Skip]。使用済みにならず、そのトリガーは失効する（007 §3-1）。他の効果の順序には影響しない。
     */
    public function skipEffect(int $playerId, int $entryId): void
    {
        $item = $this->askable($playerId, $entryId);
        $spec = $this->catalog->specFor($item->cardKey);
        if ($spec === null || !$spec->optional) {
            throw new EffectViolation('a mandatory effect cannot be skipped');
        }
        $item->status = PoolItemStatus::Skipped;
        $this->settle($playerId);
    }

    /**
     * `actOrderEffects`: 順序が未決定の衝突する集合の全要素を、解決したい順に並べた配列。確定したら変えられない。
     *
     * @param list<int> $entryIds
     */
    public function orderEffects(int $playerId, array $entryIds): void
    {
        $plan = $this->plan($playerId);
        $sorted = $entryIds;
        sort($sorted);
        foreach ($plan->kind === PlanKind::Order ? $plan->sets : [] as $set) {
            if ($set === $sorted && count(array_unique($entryIds)) === count($entryIds)) {
                foreach ($entryIds as $rank => $id) {
                    $this->requireState()->setRank($id, $rank);
                }
                $this->settle($playerId);

                return;
            }
        }
        throw new EffectViolation('the order must list every effect of one pending conflict set exactly once');
    }

    /**
     * zombie（011 §10）: 強制で選択のない効果は通常どおり解決し、`ORDER` はデフォルトの順、任意の効果は [Skip]。
     * 強制で選択のある効果（P087 の捨て）は部品の既定の答え。既定の答えが無い・検証を通らない効果は捨てる。
     * プールが空になるまで続ける。
     */
    public function zombie(int $playerId): void
    {
        $state = $this->requireState();
        for ($guard = 0; $guard < 10000; $guard++) {
            $plan = $this->plan($playerId);
            if ($plan->kind === PlanKind::Done) {
                return;
            }
            if ($plan->kind === PlanKind::Order) {
                foreach ($plan->sets as $set) {
                    foreach ($this->defaultSorted($set) as $rank => $item) {
                        $state->setRank($item->id, $rank);
                    }
                }
            } elseif ($plan->kind === PlanKind::Ask) {
                $this->zombieAnswer($plan->items[0]);
            }
            $this->settle($playerId);
        }
        throw new \LogicException('zombie did not finish the pool');
    }

    // ---- 内部: 1 人分の解決ループ（011 §5-3） ----

    private function zombieAnswer(PoolItem $item): void
    {
        $bound = $this->bind($item);
        if ($bound === null) {
            $item->status = PoolItemStatus::Dropped;

            return;
        }
        [$spec, , $part, $call] = $bound;
        if ($spec->optional) {
            $item->status = PoolItemStatus::Skipped;

            return;
        }
        $answer = $part->defaultAnswer($call);
        if (
            $answer === null
            || !$this->validator->validate($part->buildFields($call), $answer)->ok
            || !$part->validate($call, $answer)->ok
        ) {
            $item->status = PoolItemStatus::Dropped;

            return;
        }
        $this->resolve($item, $answer);
    }

    private function settleAll(): void
    {
        foreach ($this->view->playerIds() as $playerId) {
            $this->dirty[$playerId] = true;
        }
        $this->drain();
    }

    private function settle(int $playerId): void
    {
        $this->dirty[$playerId] = true;
        $this->drain();
    }

    private function drain(): void
    {
        while ($this->dirty !== []) {
            $playerId = array_key_first($this->dirty);
            unset($this->dirty[$playerId]);
            $this->advance($playerId);
        }
    }

    /**
     * `advance(p)`: 再確認に失敗した要素を捨て、問いなしに解決できる効果を解決し続け、問いが要る所で止まる。
     */
    private function advance(int $playerId): void
    {
        $state = $this->requireState();
        while (!$state->isAborted()) {
            $this->recheck($playerId);
            $plan = $this->plan($playerId);
            if ($plan->kind !== PlanKind::Auto && $plan->kind !== PlanKind::Ask) {
                return;
            }
            if ($this->dropUnavailable($plan->items)) {
                continue;
            }
            if ($plan->kind === PlanKind::Ask) {
                return;
            }
            $this->resolve($plan->items[0], null);
        }
    }

    /**
     * 解決を始める時点の再確認（007 §3-2）: 宿主が無い・稼働条件・使用済み・条件のいずれかを満たさなくなっていれば、
     * 何も起こさず捨てる。解決の途中（多段の 2 段目以降）は再評価しない。
     * 「対象なし」（`isAvailable`）は、その効果の番が来たとき（`dropUnavailable()`）に見る。衝突の判定はプール全体で行うので、
     * 先に解決する別の効果（P023 の 1 枚引くなど）で対象が生まれるかもしれない効果を、順序を聞く前に捨てないため。
     */
    private function recheck(int $playerId): void
    {
        foreach ($this->requireState()->pending($playerId) as $item) {
            if ($item->stage > 1) {
                continue;
            }
            $bound = $this->bind($item);
            if ($bound === null || !$this->collector->reacts($bound[0], $bound[1], $item->event)) {
                $item->status = PoolItemStatus::Dropped;
            }
        }
    }

    /**
     * 番が来た効果のうち、対象なし・払えない等で使えないものを捨てる（使用済みにならない。011 エッジケース）。
     *
     * @param list<PoolItem> $items
     * @return bool 1 件でも捨てたか（捨てたら計画を立て直す）
     */
    private function dropUnavailable(array $items): bool
    {
        $dropped = false;
        foreach ($items as $item) {
            $bound = $this->bind($item);
            if ($item->stage === 1 && $bound !== null && !$bound[2]->isAvailable($bound[3])) {
                $item->status = PoolItemStatus::Dropped;
                $dropped = true;
            }
        }

        return $dropped;
    }

    private function plan(int $playerId): ResolvePlan
    {
        $state = $this->requireState();
        $pending = $state->pending($playerId);
        if ($pending === []) {
            return new ResolvePlan(PlanKind::Done);
        }
        foreach ($pending as $item) {
            if ($item->stage > 1) {
                return $this->classify($item);
            }
        }

        $entries = [];
        $hosts = [];
        foreach ($pending as $item) {
            $spec = $this->catalog->specFor($item->cardKey);
            $host = $this->view->host($item->hostKind, $item->hostSrc);
            if ($spec === null || $host === null) {
                return new ResolvePlan(PlanKind::Auto, [$item]); // 宿主が無い要素は resolve() が捨てる
            }
            $entries[$item->id] = [$item, $spec, $host];
            $hosts[$item->id] = $host;
        }
        $sets = $this->conflicts->sets($entries);

        $undecided = array_values(array_filter(
            $sets,
            static fn(array $set): bool => array_filter($set, static fn(int $id): bool => $state->rank($id) === null) !== [],
        ));
        if ($undecided !== []) {
            return new ResolvePlan(PlanKind::Order, [], $undecided);
        }
        if ($sets !== []) {
            $ranked = $sets[0];
            usort($ranked, static fn(int $a, int $b): int => $state->rank($a) <=> $state->rank($b));

            return $this->classify($entries[$ranked[0]][0]);
        }

        $sorted = DefaultOrder::sort(array_map(static fn(array $e): PoolItem => $e[0], array_values($entries)), $hosts);
        $ask = [];
        foreach ($sorted as $item) {
            $plan = $this->classify($item);
            if ($plan->kind === PlanKind::Auto) {
                return $plan;
            }
            $ask[] = $item;
        }

        return new ResolvePlan(PlanKind::Ask, $ask);
    }

    /** 強制で選択のない効果は自動で解決、それ以外は問う（Q-13） */
    private function classify(PoolItem $item): ResolvePlan
    {
        $bound = $this->bind($item);
        if ($bound === null) {
            return new ResolvePlan(PlanKind::Auto, [$item]);
        }
        [$spec, , $part, $call] = $bound;
        $auto = !$spec->optional && $part->buildFields($call) === [];

        return new ResolvePlan($auto ? PlanKind::Auto : PlanKind::Ask, [$item]);
    }

    /**
     * 要素 1 件の（次の段の）解決。検証と支払いの確認は呼び出し側が済ませてある。
     * 宿主が無くなっていた・安全弁の上限に達していた場合は何も解決せずに捨てる。
     */
    private function resolve(PoolItem $item, ?Answer $answer): void
    {
        $state = $this->requireState();
        $bound = $this->bind($item);
        if ($bound === null) {
            $item->status = PoolItemStatus::Dropped;

            return;
        }
        [$spec, $host, $part, $call] = $bound;

        if ($item->stage === 1) {
            if ($state->countResolved() >= self::EFFECT_LIMIT) {
                $this->abortWindow();

                return;
            }
            $this->pay($item, $spec, $answer);
            $state->addLog(new LogEntry('effectUsed', [
                'player_id' => $item->playerId,
                'card_key' => $item->cardKey,
                'host' => $item->hostKey(),
                'mark_used' => $spec->kind === EffectKind::Activated,
            ]));
        }

        $outcome = $part->apply($call, $answer);
        foreach ($outcome->logs as $log) {
            $state->addLog($log);
        }
        foreach ($outcome->stagedLinks as $staged) {
            $state->stage($staged);
        }
        foreach ($outcome->events as $event) {
            $this->collect($event);
        }

        $item->stage++;
        if ($item->stage > $part->stages($spec)) {
            $item->status = PoolItemStatus::Done;
            $state->noteResolved();
            if ($spec->kind === EffectKind::Activated) {
                $this->mutator->markUsed($host);
            }
        }
    }

    /** 安全弁: 残りの要素を捨て、窓の強制終了をログに残す（007 §8-3） */
    private function abortWindow(): void
    {
        $state = $this->requireState();
        foreach ($state->items() as $item) {
            if ($item->isPending()) {
                $item->status = PoolItemStatus::Dropped;
            }
        }
        $state->abort();
        $state->addLog(new LogEntry('windowLimitReached', ['limit' => self::EFFECT_LIMIT]));
    }

    /** [Use] の支払い。売却（答えに同梱）→ C1 のマヤ。C2・C3 は部品が `apply()` で扱う */
    private function pay(PoolItem $item, EffectSpec $spec, ?Answer $answer): void
    {
        if ($answer !== null && $answer->sell !== []) {
            $this->mutator->sellCards($item->playerId, $answer->sell);
        }
        if ($spec->cost !== null && $spec->cost->kind === CostKind::PayMaya) {
            $this->mutator->payMaya($item->playerId, $spec->cost->amount);
        }
    }

    /** 売却するカードが手札にあり重複しないこと、C1 のマヤが（売却込みで）払えること */
    private function checkSale(int $playerId, EffectSpec $spec, Answer $answer, bool $firstStage): void
    {
        $sell = $answer->sell;
        if ($sell !== [] && (!$firstStage || $spec->cost === null)) {
            throw new EffectViolation('nothing to pay for');
        }
        if (count(array_unique($sell)) !== count($sell) || array_diff($sell, $this->view->hand($playerId)) !== []) {
            throw new EffectViolation('invalid cards to sell');
        }
        if ($firstStage && $spec->cost !== null && $spec->cost->kind === CostKind::PayMaya) {
            $funds = $this->view->maya($playerId) + count($sell) * $this->view->sellPrice($playerId);
            if ($funds < $spec->cost->amount) {
                throw new EffectViolation('not enough maya');
            }
        }
    }

    // ---- 内部: 問いの組み立て ----

    private function askable(int $playerId, int $entryId): PoolItem
    {
        $plan = $this->plan($playerId);
        if ($plan->kind === PlanKind::Order) {
            throw new EffectViolation('the order of conflicting effects must be decided first');
        }
        foreach ($plan->kind === PlanKind::Ask ? $plan->items : [] as $item) {
            if ($item->id === $entryId) {
                return $item;
            }
        }
        throw new EffectViolation('this effect cannot be answered now');
    }

    /**
     * @param list<int> $set
     */
    private function orderQuestion(array $set): Question
    {
        $state = $this->requireState();
        $items = array_map(fn(int $id): PoolItem => $state->item($id) ?? throw new \LogicException("pool item $id"), $set);
        $hosts = [];
        foreach ($items as $item) {
            $host = $this->view->host($item->hostKind, $item->hostSrc);
            if ($host !== null) {
                $hosts[$item->id] = $host;
            }
        }
        $default = DefaultOrder::sort($items, $hosts);

        // 初期の並び: 前に決めた相対順（連鎖で新しい効果が加わった場合）を保ち、新しい効果をデフォルトの順で後ろに足す
        $ranked = array_values(array_filter($default, static fn(PoolItem $i): bool => $state->rank($i->id) !== null));
        usort($ranked, static fn(PoolItem $a, PoolItem $b): int => $state->rank($a->id) <=> $state->rank($b->id));
        $fresh = array_values(array_filter($default, static fn(PoolItem $i): bool => $state->rank($i->id) === null));
        $initial = [...$ranked, ...$fresh];

        return Question::order(
            array_map(fn(PoolItem $i): array => [
                'entryId' => $i->id,
                'cardKey' => $i->cardKey,
                'forced' => !($this->catalog->specFor($i->cardKey)->optional ?? false),
            ], $initial),
            array_map(static fn(PoolItem $i): int => $i->id, $default),
        );
    }

    private function effectQuestion(PoolItem $item): Question
    {
        [$spec, , $part, $call] = $this->bind($item) ?? throw new \LogicException("pool item {$item->id} has no host");
        $playerId = $item->playerId;
        $maya = $this->view->maya($playerId);
        $cost = ['kind' => 'NONE'];
        if ($spec->cost !== null && $item->stage === 1) {
            $cost = ['kind' => $spec->cost->kind->value];
            if ($spec->cost->kind === CostKind::PayMaya) {
                $sellable = count($this->view->hand($playerId)) * $this->view->sellPrice($playerId);
                $cost += [
                    'amount' => $spec->cost->amount,
                    'canPay' => $maya >= $spec->cost->amount,
                    'shortfall' => max(0, $spec->cost->amount - $maya),
                    'canPayBySelling' => $maya + $sellable >= $spec->cost->amount,
                ];
            } elseif ($spec->cost->kind === CostKind::PayMayaTier) {
                $cost += ['tiers' => $spec->cost->tiers, 'canPay' => $spec->cost->tiers !== [] && $maya >= min(array_keys($spec->cost->tiers))];
            } else {
                $cost += ['amount' => $spec->cost->amount];
            }
        }

        return Question::effect($item->id, $item->cardKey, !$spec->optional, $cost, $part->buildFields($call), $part->preview($call));
    }

    /**
     * @param list<int> $set
     * @return list<PoolItem>
     */
    private function defaultSorted(array $set): array
    {
        $state = $this->requireState();
        $items = array_map(fn(int $id): PoolItem => $state->item($id) ?? throw new \LogicException("pool item $id"), $set);
        $hosts = [];
        foreach ($items as $item) {
            $host = $this->view->host($item->hostKind, $item->hostSrc);
            if ($host !== null) {
                $hosts[$item->id] = $host;
            }
        }

        return DefaultOrder::sort($items, $hosts);
    }

    /**
     * @return array{EffectSpec, EffectHost, ResultPart, PartCall}|null
     */
    private function bind(PoolItem $item): ?array
    {
        $spec = $this->catalog->specFor($item->cardKey);
        $host = $this->view->host($item->hostKind, $item->hostSrc);
        if ($spec === null || $host === null) {
            return null;
        }
        $part = $this->parts->result($spec->part);

        return [$spec, $host, $part, new PartCall($this->ctx, $item, $spec, $host, $item->stage)];
    }

    private function report(): WindowReport
    {
        $state = $this->requireState();

        return new WindowReport(
            $this->playersNeedingInput(),
            $state->takeStaged(),
            $state->items() !== [],
            $state->isAborted(),
        );
    }

    private function requireState(): WindowState
    {
        return $this->state ?? throw new \LogicException('no window is open');
    }
}
