<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Query;

use Bga\Games\ProjectEdgeworth\Effects\Context\GameMutator;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameView;
use Bga\Games\ProjectEdgeworth\Effects\EffectViolation;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartRegistry;
use Bga\Games\ProjectEdgeworth\Flow\Action\StepMode;
use Bga\Games\ProjectEdgeworth\Material\CostKind;
use Bga\Games\ProjectEdgeworth\Material\EffectCatalog;
use Bga\Games\ProjectEdgeworth\Material\EffectKind;
use Bga\Games\ProjectEdgeworth\Material\Hook;
use Bga\Games\ProjectEdgeworth\Material\PartCategory;

/**
 * `PROPOSAL_OPTIONS`（P068・P070・P072。011 §6-5）: 企画の「残すカードの選択」（状態 91）に含める効果。窓を開かない。
 *
 * - `plan()`: 使えるオプション（P070 の追加の捨て、P072 の残す枚数の購入）と、P068 が効いているか。
 * - `keepCount()`: P072 の購入を含む残す枚数（引いた枚数を超えない）。
 * - `validate()` / `apply()`: 答え `actConfirmKeep` の `optionUses` の検証と実行。
 *
 * 答えの形（`optionUses`）: 宿主の鍵 => 値。P070 は捨てるカード ID、P072 は払う額（4 / 7 / 10）。P068 は常時の修正なので鍵は要らず、
 * 代わりに `handDiscard`（手札から捨てるカード）を渡す。使わないオプションは入れない（zombie は空）。
 *
 * 計算の約束:
 * - P068 で手札を捨てるとき、捨てる枚数は変わらない。残す枚数を k、引いた枚数を n とすると、捨てるのは n − k 枚で、
 *   引いたカードのうち残すのは k + (手札から捨てる枚数) 枚。
 * - P070 の追加の捨ては、残すカードを手札に入れた後の手札（残したカードを含む）から選ぶ。企画中に引いたカードである必要はない（FAQ 行 28）。
 *   `apply()` は残すカードを手札に入れた後に呼ぶ。
 * - 捨ては売却ではないので、売却額の修正（P077・R009）は適用しない（FAQ 行 31・70）。この類は `sellPrice` を問い合わせない。
 * - 支払いは、P072 の額の合計が所持マヤ（売却を同梱すれば売却分を含む）で足りること。P070 の +7 は支払いに数えない。
 */
final class ProposalOptions
{
    public function __construct(
        private readonly GameView $view,
        private readonly GameMutator $mutator,
        private readonly EffectCatalog $catalog,
        private readonly PartRegistry $parts,
    ) {
    }

    public function plan(int $playerId, StepMode $mode): ProposalOptionPlan
    {
        $q = new QueryContext($playerId, mode: $mode);
        $plan = new ProposalOptionPlan();
        foreach ($this->view->hosts($playerId) as $host) {
            $spec = $this->catalog->specFor($host->cardKey);
            if ($spec === null || $spec->hook !== Hook::ProposalOptions) {
                continue;
            }
            if (($spec->activeOnly && !$host->active) || ($spec->kind === EffectKind::Activated && $host->used)) {
                continue;
            }
            if ($spec->part->category() === PartCategory::Modifier) {
                $part = $this->parts->modifier($spec->part);
                if ($part->appliesTo($q, $spec, $host)) {
                    $plan = $part->modify($q, $spec, $host, $plan);
                }
            } elseif ($spec->cost?->kind === CostKind::DiscardCost) {
                $plan = $plan->withOption(new ProposalOption($host->key(), $spec->cardKey, ProposalOption::EXTRA_DISCARD, (int) ($spec->params['amount'] ?? 0)));
            }
        }

        return $plan;
    }

    /** 残す枚数（P072 の購入を含む。引いた枚数を超えない） */
    public function keepCount(ProposalOptionPlan $plan, int $baseKeep, int $drawnCount, array $uses = []): int
    {
        $keep = $baseKeep;
        foreach ($plan->options as $option) {
            if ($option->kind === ProposalOption::KEEP_PURCHASE && isset($uses[$option->hostKey])) {
                $keep += $option->tiers[(int) $uses[$option->hostKey]] ?? 0;
            }
        }

        return max(0, min($keep, $drawnCount));
    }

    /**
     * 91 の問いに載せる表示用の形。
     *
     * @param list<int> $drawn 企画で引いたカード
     * @return array<string, mixed>
     */
    public function describe(int $playerId, ProposalOptionPlan $plan, array $drawn): array
    {
        $maya = $this->view->maya($playerId);
        $sellable = count($this->view->hand($playerId)) * $this->view->sellPrice($playerId);
        $options = [];
        foreach ($plan->options as $option) {
            $row = $option->toArray();
            if ($option->kind === ProposalOption::KEEP_PURCHASE) {
                $row['affordable'] = array_values(array_filter(array_keys($option->tiers), static fn(int $cost): bool => $cost <= $maya));
                $row['affordableBySelling'] = array_values(array_filter(array_keys($option->tiers), static fn(int $cost): bool => $cost <= $maya + $sellable));
                $row['maxKeep'] = count($drawn);
            }
            $options[] = $row;
        }

        return ['handDiscard' => $plan->handDiscard, 'handCandidates' => $plan->handDiscard ? $this->view->hand($playerId) : [], 'options' => $options];
    }

    /**
     * 答えを検証する。不正なら `EffectViolation`。
     *
     * @param list<int> $drawn 企画で引いたカード
     * @param list<int> $keep 引いたカードのうち残すカード
     * @param list<int> $handDiscard P068: 手札から捨てるカード
     * @param array<string, mixed> $uses 宿主の鍵 => 値
     * @param list<int> $sell 同梱する売却
     */
    public function validate(
        int $playerId,
        ProposalOptionPlan $plan,
        int $baseKeep,
        array $drawn,
        array $keep,
        array $handDiscard,
        array $uses,
        array $sell = [],
    ): void {
        $hand = $this->view->hand($playerId);
        $unique = static fn(array $list): bool => count($list) === count(array_unique($list));

        foreach ($uses as $key => $value) {
            $option = $plan->option((string) $key) ?? throw new EffectViolation("option is not available: $key");
            if ($option->kind === ProposalOption::KEEP_PURCHASE && !isset($option->tiers[(int) $value])) {
                throw new EffectViolation('invalid payment tier');
            }
            if ($option->kind === ProposalOption::EXTRA_DISCARD && !in_array((int) $value, [...$hand, ...$keep], true)) {
                throw new EffectViolation('the extra discard must be a card in hand');
            }
        }
        if (!$unique($keep) || array_diff($keep, $drawn) !== []) {
            throw new EffectViolation('keep only cards that were drawn');
        }
        if ($handDiscard !== [] && !$plan->handDiscard) {
            throw new EffectViolation('discarding from hand needs Shannon Entropy');
        }
        if (!$unique($handDiscard) || array_diff($handDiscard, $hand) !== []) {
            throw new EffectViolation('invalid cards to discard from hand');
        }
        $keepCount = $this->keepCount($plan, $baseKeep, count($drawn), $uses);
        if (count($keep) !== min(count($drawn), $keepCount + count($handDiscard))) {
            throw new EffectViolation('keep exactly ' . min(count($drawn), $keepCount + count($handDiscard)) . ' card(s)');
        }
        $extra = array_map(static fn(mixed $v): int => (int) $v, array_values(array_filter(
            $uses,
            fn(mixed $v, mixed $k): bool => $plan->option((string) $k)?->kind === ProposalOption::EXTRA_DISCARD,
            ARRAY_FILTER_USE_BOTH,
        )));
        $claimed = [...$handDiscard, ...$extra, ...$sell];
        if (!$unique($claimed)) {
            throw new EffectViolation('a card cannot be used twice');
        }
        if (array_diff($sell, $hand) !== []) {
            throw new EffectViolation('invalid cards to sell');
        }
        $pay = $this->payment($plan, $uses);
        if ($this->view->maya($playerId) + count($sell) * $this->view->sellPrice($playerId) < $pay) {
            throw new EffectViolation('not enough maya');
        }
    }

    /**
     * 実行する: 売却 → 支払い → 手札からの捨て（P068・P070）→ P070 の +7 → 使用済み。残すカードは手札に入れた後に呼ぶ。
     *
     * @param list<int> $handDiscard
     * @param array<string, mixed> $uses
     * @param list<int> $sell
     */
    public function apply(int $playerId, ProposalOptionPlan $plan, array $handDiscard, array $uses, array $sell = []): void
    {
        if ($sell !== []) {
            $this->mutator->sellCards($playerId, $sell);
        }
        $pay = $this->payment($plan, $uses);
        if ($pay > 0) {
            $this->mutator->payMaya($playerId, $pay);
        }
        $discard = $handDiscard;
        foreach ($uses as $key => $value) {
            if ($plan->option((string) $key)?->kind === ProposalOption::EXTRA_DISCARD) {
                $discard[] = (int) $value;
            }
        }
        if ($discard !== []) {
            $this->mutator->discardFromHand($playerId, $discard);
        }
        foreach ($uses as $key => $value) {
            $option = $plan->option((string) $key);
            if ($option?->kind === ProposalOption::EXTRA_DISCARD) {
                $this->mutator->gainMaya($playerId, $option->reward);
            }
            [$kind, $src] = explode(':', (string) $key) + [1 => '0'];
            $host = $this->view->host($kind, (int) $src);
            if ($host !== null) {
                $this->mutator->markUsed($host);
            }
        }
    }

    /**
     * @param array<string, mixed> $uses
     */
    private function payment(ProposalOptionPlan $plan, array $uses): int
    {
        $pay = 0;
        foreach ($uses as $key => $value) {
            if ($plan->option((string) $key)?->kind === ProposalOption::KEEP_PURCHASE) {
                $pay += (int) $value;
            }
        }

        return $pay;
    }
}
