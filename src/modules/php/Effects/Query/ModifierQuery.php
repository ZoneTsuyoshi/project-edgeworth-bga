<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Query;

use Bga\Games\projectedgeworth\Effects\Condition\ConditionContext;
use Bga\Games\projectedgeworth\Effects\Condition\ConditionEvaluator;
use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Context\GameView;
use Bga\Games\projectedgeworth\Effects\Parts\PartRegistry;
use Bga\Games\projectedgeworth\Material\EffectCatalog;
use Bga\Games\projectedgeworth\Material\EffectKind;
use Bga\Games\projectedgeworth\Material\EffectSpec;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\HookKind;
use Bga\Games\projectedgeworth\Material\PartCategory;

/**
 * 修正の問い合わせ（011 §3-2）の共通の骨組み。プレイヤーの効果のカードから、そのフックの修正・禁止を集めて畳み込む。
 *
 * - 稼働条件 `ACTIVE_ONLY` は、数量を決める時点・コミット前の状態で評価する（007 §3-2。宿主の `active` を見る）。
 * - 起動（`ACTIVATED`）の修正のうち [Use] / [Skip] を聞くもの（`optional`）は、`$useMods` に宿主の鍵（`EffectHost::key()`）が
 *   入っているものだけ畳み込む（007 §5-1、011 §6-3）。聞かないもの（R011・R014。`optional` が偽）は常に畳み込む。
 *   使用済みの宿主は、そもそも `$useMods` に入れられない（呼び出し側の選択肢から外す）。ここでは使用済みを理由に落とすことはしない。
 * - 配備コストの自己修正（P026、`params['scope'] = 'SELF_CARD'`）は、配備しようとするカード自身（まだ衛星に付いていない）を
 *   `$selfHost` で渡したときだけ働く。すでに衛星にある P026 は他のカードの配備コストを変えない（011 エッジケース）。
 */
final class ModifierQuery
{
    public function __construct(
        private readonly GameView $view,
        private readonly EffectCatalog $catalog,
        private readonly PartRegistry $parts,
        private readonly ConditionEvaluator $conditions,
    ) {
    }

    /**
     * @param list<string> $useMods 使う起動の修正の宿主の鍵
     */
    public function fold(Hook $hook, QueryContext $query, mixed $base, array $useMods = [], ?EffectHost $selfHost = null): mixed
    {
        $value = $base;
        foreach ($this->candidates($hook, $query, $useMods, $selfHost, PartCategory::Modifier) as [$spec, $host]) {
            $part = $this->parts->modifier($spec->part);
            if ($part->appliesTo($query, $spec, $host)) {
                $value = $part->modify($query, $spec, $host, $value);
            }
        }

        return $value;
    }

    /**
     * 起動の修正のうち、この問い合わせで [Use] を選べるもの（`optional` で、今ラウンド未使用で、稼働条件・条件を満たす）。
     * 配備・打ち上げ・通信確立の選択に同梱する `USE_MODS` の候補（011 §6-3）。
     *
     * @return list<array{EffectSpec, EffectHost}>
     */
    public function useModCandidates(Hook $hook, QueryContext $query, ?EffectHost $selfHost = null): array
    {
        $found = [];
        foreach ($this->candidates($hook, $query, [], $selfHost, PartCategory::Modifier, true) as [$spec, $host]) {
            if (!$host->used && $this->parts->modifier($spec->part)->appliesTo($query, $spec, $host)) {
                $found[] = [$spec, $host];
            }
        }

        return $found;
    }

    /** `ALLOWED`: 禁止効果が 1 つでも効いていれば真 */
    public function forbidden(QueryContext $query): bool
    {
        foreach ($this->candidates(Hook::Allowed, $query, [], null, PartCategory::Forbid) as [$spec, $host]) {
            if ($this->parts->forbid($spec->part)->forbids($query, $spec, $host)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $useMods
     * @param bool $listOptions 真なら [Use] / [Skip] を聞く起動の修正だけを、選ばれたかどうかに依らず挙げる（`useModCandidates()`）
     * @return list<array{EffectSpec, EffectHost}>
     */
    private function candidates(Hook $hook, QueryContext $query, array $useMods, ?EffectHost $selfHost, PartCategory $category, bool $listOptions = false): array
    {
        if ($hook->kind() !== HookKind::Query) {
            throw new \InvalidArgumentException("{$hook->value} is not a query hook");
        }
        $hosts = $this->view->hosts($query->playerId);
        $found = [];
        foreach ([...$hosts, ...($selfHost === null ? [] : [$selfHost])] as $host) {
            $spec = $this->catalog->specFor($host->cardKey);
            if ($spec === null || $spec->hook !== $hook || $spec->part->category() !== $category) {
                continue;
            }
            $self = ($spec->params['scope'] ?? null) === 'SELF_CARD';
            if ($self !== ($host === $selfHost)) {
                continue;
            }
            // 選択（[Use] / [Skip]）のある起動の修正だけが `$useMods` を要る。選択のない起動の修正（R011・R014 など `optional` が偽）は常に効く
            $optionalMod = $spec->kind === EffectKind::Activated && $spec->optional;
            if ($listOptions ? !$optionalMod : ($optionalMod && !in_array($host->key(), $useMods, true))) {
                continue;
            }
            if ($spec->activeOnly && !$host->active) {
                continue;
            }
            if (!$this->conditions->holds($spec, new ConditionContext($host, null, $query))) {
                continue;
            }
            $found[] = [$spec, $host];
        }

        return $found;
    }
}
