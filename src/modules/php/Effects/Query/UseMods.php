<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Query;

use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameMutator;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameView;
use Bga\Games\ProjectEdgeworth\Effects\EffectViolation;
use Bga\Games\ProjectEdgeworth\Effects\Question\Field;
use Bga\Games\ProjectEdgeworth\Effects\Question\FieldType;
use Bga\Games\ProjectEdgeworth\Material\Hook;

/**
 * 起動の修正（`USE_MODS`。P019〜P021・P036・R021〜R025）を、コストを払う選択に同梱するための部品（011 §6-3、007 §5-1）。
 *
 * 起動の修正はプールの要素にならず、配備・打ち上げ・通信確立の選択（状態 61・62・71・81・82）と、効果が行う配備・通信確立（R09・R11）の
 * 問いに `USE_MODS` として同梱する。呼び方:
 *
 * 1. args: `options()` で候補（宿主の鍵・カード・使うと何マヤ安くなるか）を作り、`field()` で問いのフィールドにする。
 * 2. 検証: 答えの `useMods`（宿主の鍵の配列）を `validate()` に渡す。候補にない鍵・重複・同じステップの別の選択との重複は弾く（`validateStep()`）。
 *    コストは `EngineActionHooks::useMods()` で使う鍵を渡してから問い合わせる。
 * 3. コミット: そのコストを実際に払うコミットの時点で `commit()` が使用済みにする（コミットされなかった選択では付かない）。
 *
 * メインとボーナスは別のステップで、メインで使った起動の修正は使用済みになるのでボーナスでは候補に出ない（各ラウンド 1 回。FAQ 行 53）。
 */
final class UseMods
{
    public function __construct(
        private readonly ModifierQuery $query,
        private readonly EffectQueries $queries,
        private readonly GameView $view,
        private readonly GameMutator $mutator,
    ) {
    }

    /**
     * この選択で使える起動の修正。`reduction` は、これ 1 つだけを使ったときに（下限 0 の前に）引ける量。
     *
     * @return list<array{hostKey: string, cardKey: string, reduction: int}>
     */
    public function options(Hook $hook, QueryContext $q, ?EffectHost $selfHost = null): array
    {
        $options = [];
        foreach ($this->query->useModCandidates($hook, $q, $selfHost) as [$spec, $host]) {
            $options[] = [
                'hostKey' => $host->key(),
                'cardKey' => $spec->cardKey,
                'reduction' => $this->cost($hook, $q, [$host->key()], $selfHost)->reduction(),
            ];
        }

        return $options;
    }

    /**
     * 使わない場合の合計と、使う鍵ごと・全部使った場合の合計（下限 0 を適用した後）。args の `preview` に入れる。
     *
     * @param int $base 基本コスト（配備は稼働コストを含めた額、打ち上げは衛星カードのコスト、通信確立は区域＋先駆）
     * @return array{without: int, with: array<string, int>, all: int}
     */
    public function quote(Hook $hook, QueryContext $q, int $base, ?EffectHost $selfHost = null): array
    {
        $keys = array_column($this->options($hook, $q, $selfHost), 'hostKey');
        $with = [];
        foreach ($keys as $key) {
            $with[$key] = $this->cost($hook, $q, [$key], $selfHost, $base)->total();
        }

        return [
            'without' => $this->cost($hook, $q, [], $selfHost, $base)->total(),
            'with' => $with,
            'all' => $this->cost($hook, $q, $keys, $selfHost, $base)->total(),
        ];
    }

    /**
     * @param list<array{hostKey: string, cardKey: string, reduction: int}> $options
     */
    public function field(array $options): Field
    {
        $keys = array_column($options, 'hostKey');

        return new Field('useMods', FieldType::UseMods, $keys, 0, count($keys), []);
    }

    /**
     * 1 つの選択の `useMods` を検証する。
     *
     * @param list<mixed> $chosen 答えの `useMods`
     * @param list<array{hostKey: string, cardKey: string, reduction: int}> $options
     * @return list<string> 検証済みの鍵
     */
    public function validate(array $chosen, array $options): array
    {
        $allowed = array_column($options, 'hostKey');
        $keys = [];
        foreach ($chosen as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true) || in_array($key, $keys, true)) {
                throw new EffectViolation('invalid modifier to use');
            }
            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * 同じステップの複数の選択（R005 の 2 枚など）で、同じ起動の修正を 2 回使っていないこと（各ラウンド 1 回）。
     *
     * @param list<list<string>> $perChoice
     */
    public function validateStep(array $perChoice): void
    {
        $all = array_merge([], ...$perChoice);
        if (count($all) !== count(array_unique($all))) {
            throw new EffectViolation('a modifier can be used once per round');
        }
    }

    /**
     * そのコストを実際に払うコミットの時点で使用済みにする。
     *
     * @param list<string> $hostKeys
     */
    public function commit(array $hostKeys): void
    {
        foreach ($hostKeys as $key) {
            [$kind, $src] = explode(':', $key) + [1 => '0'];
            $host = $this->view->host($kind, (int) $src);
            if ($host === null) {
                throw new EffectViolation('the modifier card is gone');
            }
            $this->mutator->markUsed($host);
        }
    }

    /**
     * @param list<string> $useMods
     */
    private function cost(Hook $hook, QueryContext $q, array $useMods, ?EffectHost $selfHost, int $base = 0): CostBreakdown
    {
        return match ($hook) {
            Hook::DeployCost => $this->queries->deployCost($q, $base, $useMods, $selfHost),
            Hook::LaunchCost => $this->queries->launchCost($q, $base, $useMods),
            Hook::LinkCost => $this->queries->linkCost($q, $base, $useMods),
            default => throw new \InvalidArgumentException("{$hook->value} has no activated cost modifier"),
        };
    }
}
