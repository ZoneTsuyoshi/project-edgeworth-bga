<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts\Result;

use Bga\Games\ProjectEdgeworth\Data\ProjectCardRepository;
use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameEvent;
use Bga\Games\ProjectEdgeworth\Effects\LogEntry;
use Bga\Games\ProjectEdgeworth\Effects\Outcome;
use Bga\Games\ProjectEdgeworth\Effects\Parts\AbstractResultPart;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartCall;
use Bga\Games\ProjectEdgeworth\Effects\Query\EffectQueries;
use Bga\Games\ProjectEdgeworth\Effects\Query\QueryContext;
use Bga\Games\ProjectEdgeworth\Effects\Query\UseMods;
use Bga\Games\ProjectEdgeworth\Effects\Question\Answer;
use Bga\Games\ProjectEdgeworth\Effects\Question\Field;
use Bga\Games\ProjectEdgeworth\Effects\Question\FieldType;
use Bga\Games\ProjectEdgeworth\Effects\Question\ValidationResult;
use Bga\Games\ProjectEdgeworth\Flow\Action\DeployPhase;
use Bga\Games\ProjectEdgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\ProjectEdgeworth\Material\CostKind;
use Bga\Games\ProjectEdgeworth\Material\Hook;
use Bga\Games\ProjectEdgeworth\Material\ProjectCards;

/**
 * R09 `DEPLOY_FROM_HAND`: 手札のプロジェクトカードを配備コストを支払って衛星に配備する（P011・P045・P074・R005）。
 *
 * パラメータ:
 * - `filterType`: 配備できるカードのタイプ（P011 は DEVICE。省略は全て）。
 * - `targetScope`: `THIS`（このカードのある衛星。P045・R005）/ `ANY_OWN`（自分の好きな衛星。P011・P074）。
 * - `surcharge`: 稼働中の衛星への追加配備コスト（+5）が必要か（P011・P045・P074 は必要。FAQ 行 20。R005 は不要。FAQ 行 69）。
 * - `maxCards`: 一度に配備できる枚数（R005 は 2。既定 1）。
 * - `fireLaunch`: 真なら配備したカードの `ON_LAUNCH` も起こす（R005。FAQ 行 69）。`ON_DEPLOY` は `DEPLOYED` の自身への反応で起きる。
 *
 * 問い: `pairs`（(カード番号, 衛星 ID) の組。サーバーが有効な組を列挙する）、`useMods`（起動の修正 P019〜P021 を使うか。011 §6-3）、
 * 支払いが C3（P074）なら `discard`（捨てるカード 1 枚。配備するカードとは別。売却でも使えない）。
 * 有効な組: 手札のカード（`filterType`）× 衛星（`targetScope`）で、4 枚未満で、配備できない衛星（F01 `DEPLOY_TO_THIS`、P033）でないもの
 * （FAQ 行 55）、そして売却を使えば払えるもの。コストは 007 §5-2（基本 + 追加配備コスト − 修正、下限 0）で、配備コストの修正（P010 ほか）が効く。
 * 同じ衛星に 2 枚目を配備するときは、1 枚目の配備後のカード枚数で計算する。
 * `useMods` の鍵は、答えの組の順に、それを使える最初の組に割り当てる（各ラウンド 1 回なので 1 枚にだけ効く）。
 *
 * 支払いはこの部品の `apply()` が行う（売却の同梱はエンジンが先に実行）。配備のたびに燃料庫の数が変わるので、c は `FuelAdjust` で直す（N-1）。
 * 配備は `DEPLOYED` を起こし、連鎖する（007 §8-1）。
 */
final class DeployFromHandPart extends AbstractResultPart
{
    public function __construct(
        private readonly EffectQueries $queries,
        private readonly UseMods $useMods,
    ) {
    }

    public function acceptsSale(PartCall $call): bool
    {
        return true;
    }

    public function isAvailable(PartCall $call): bool
    {
        return $this->candidates($call) !== [];
    }

    public function buildFields(PartCall $call): array
    {
        $pairs = $this->candidates($call);
        $fields = [new Field('pairs', FieldType::Pairs, $pairs, 1, max(1, (int) ($call->spec->params['maxCards'] ?? 1)))];
        $keys = [];
        foreach ($pairs as [$cardNo, $satelliteId]) {
            foreach ($this->modOptions($call, $cardNo, $satelliteId, 0) as $option) {
                $keys[$option['hostKey']] = true;
            }
        }
        if ($keys !== []) {
            $fields[] = new Field('useMods', FieldType::UseMods, array_keys($keys), 0, count($keys));
        }
        if ($this->discardCount($call) > 0) {
            $fields[] = new Field('discard', FieldType::Cards, $call->ctx->view->hand($call->host->playerId), $this->discardCount($call), $this->discardCount($call));
        }

        return $fields;
    }

    public function validate(PartCall $call, Answer $answer): ValidationResult
    {
        $view = $call->ctx->view;
        $playerId = $call->host->playerId;
        $pairs = $this->pairsOf($answer);
        $cards = array_column($pairs, 0);
        if (count($cards) !== count(array_unique($cards))) {
            return ValidationResult::error('a card can be deployed once');
        }
        $discard = $this->discardOf($answer);
        if (
            count($discard) !== $this->discardCount($call)
            || array_intersect($discard, $cards) !== []
            || array_intersect($answer->sell, $cards) !== []
            || array_intersect($answer->sell, $discard) !== []
        ) {
            return ValidationResult::error('a card cannot be deployed, discarded and sold at once');
        }
        $perSatellite = [];
        foreach ($pairs as [, $satelliteId]) {
            $perSatellite[$satelliteId] = ($perSatellite[$satelliteId] ?? 0) + 1;
        }
        foreach ($perSatellite as $satelliteId => $count) {
            if (count($view->deployedCards($satelliteId)) + $count > ProjectCardRepository::MAX_DEPLOYED) {
                return ValidationResult::error('a satellite holds up to 4 cards');
            }
        }
        $plan = $this->plan($call, $pairs, $answer->fields['useMods'] ?? []);
        if ($plan === null) {
            return ValidationResult::error('invalid modifier to use');
        }
        $total = array_sum(array_column($plan, 'cost'));
        if ($view->maya($playerId) + count($answer->sell) * $view->sellPrice($playerId) < $total) {
            return ValidationResult::error('not enough maya');
        }

        return ValidationResult::ok();
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $answer ??= new Answer();
        $mutator = $call->ctx->mutator;
        $playerId = $call->host->playerId;
        $pairs = $this->pairsOf($answer);
        $plan = $this->plan($call, $pairs, $answer->fields['useMods'] ?? []) ?? [];
        $total = array_sum(array_column($plan, 'cost'));

        $discard = $this->discardOf($answer);
        if ($discard !== []) {
            $mutator->discardFromHand($playerId, $discard);
        }
        if ($total > 0) {
            $mutator->payMaya($playerId, $total);
        }
        $this->useMods->commit(array_values(array_unique(array_merge([], ...array_column($plan, 'mods')))));

        $events = [];
        $logs = [];
        foreach ($plan as $step) {
            FuelAdjust::around($call->ctx, $step['sat'], static function () use ($mutator, $playerId, $step): void {
                $mutator->deployFromHand($playerId, $step['card'], $step['sat']);
            });
            $type = ProjectCards::get(sprintf('P%03d', $step['card']))->type;
            $events[] = new GameEvent(Hook::Deployed, $playerId, $step['sat'], $step['card'], $type);
            if (!empty($call->spec->params['fireLaunch'])) {
                $events[] = new GameEvent(Hook::Launched, $playerId, $step['sat'], $step['card'], $type);
            }
            $logs[] = new LogEntry('cardDeployed', [
                'player_id' => $playerId,
                'card_key' => $call->spec->cardKey,
                'card_id' => $step['card'],
                'sat_id' => $step['sat'],
                'cost' => $step['cost'],
            ]);
        }

        return new Outcome($events, $logs);
    }

    public function preview(PartCall $call): array
    {
        $costs = [];
        foreach ($this->candidates($call) as [$cardNo, $satelliteId]) {
            $costs[] = ['card' => $cardNo, 'sat' => $satelliteId, 'cost' => $this->cost($call, $cardNo, $satelliteId, 0, [])];
        }

        return ['costs' => $costs];
    }

    // ---- 内部 ----

    /** @return int 支払い C3 で捨てる枚数（P074 は 1。なければ 0） */
    private function discardCount(PartCall $call): int
    {
        return $call->spec->cost?->kind === CostKind::DiscardCost ? $call->spec->cost->amount : 0;
    }

    /**
     * 有効な (カード番号, 衛星 ID) の組（売却を使えば払える組だけ）。
     *
     * @return list<array{int, int}>
     */
    private function candidates(PartCall $call): array
    {
        $view = $call->ctx->view;
        $playerId = $call->host->playerId;
        $hand = $view->hand($playerId);
        $reserved = 1 + $this->discardCount($call);   // 配備するカードと、捨てるカードは売却に使えない
        if (count($hand) < $reserved) {
            return [];
        }
        $funds = $view->maya($playerId) + (count($hand) - $reserved) * $view->sellPrice($playerId);
        $scope = $call->spec->params['targetScope'] ?? 'ANY_OWN';
        $satellites = $scope === 'THIS'
            ? ($call->host->satelliteId === null ? [] : [$call->host->satelliteId])
            : $view->satelliteIds($playerId);
        $filter = $call->spec->params['filterType'] ?? null;

        $pairs = [];
        foreach ($hand as $cardNo) {
            if ($filter !== null && ProjectCards::get(sprintf('P%03d', $cardNo))->type->value !== $filter) {
                continue;
            }
            foreach ($satellites as $satelliteId) {
                if (!$this->canReceive($call, $satelliteId, 0)) {
                    continue;
                }
                $keys = array_column($this->modOptions($call, $cardNo, $satelliteId, 0), 'hostKey');
                if ($this->cost($call, $cardNo, $satelliteId, 0, $keys) <= $funds) {
                    $pairs[] = [$cardNo, $satelliteId];
                }
            }
        }

        return $pairs;
    }

    /** その衛星に（すでに `$extra` 枚が追加される前提で）もう 1 枚配備できるか（4 枚未満・F01 でない） */
    private function canReceive(PartCall $call, int $satelliteId, int $extra): bool
    {
        $view = $call->ctx->view;

        return $view->satelliteState($satelliteId) !== null
            && count($view->deployedCards($satelliteId)) + $extra < ProjectCardRepository::MAX_DEPLOYED
            && $this->queries->allowed(new QueryContext($call->host->playerId, $satelliteId, action: ForbiddenAction::DeployTo));
    }

    /**
     * @param list<string> $keys 使う起動の修正の宿主の鍵
     */
    private function cost(PartCall $call, int $cardNo, int $satelliteId, int $extra, array $keys): int
    {
        [$query, $self, $base] = $this->costInputs($call, $cardNo, $satelliteId, $extra);

        return $this->queries->deployCost($query, $base, $keys, $self)->total();
    }

    /**
     * @return list<array{hostKey: string, cardKey: string, reduction: int}>
     */
    private function modOptions(PartCall $call, int $cardNo, int $satelliteId, int $extra): array
    {
        [$query, $self] = $this->costInputs($call, $cardNo, $satelliteId, $extra);

        return $this->useMods->options(Hook::DeployCost, $query, $self);
    }

    /**
     * @return array{QueryContext, EffectHost, int} 問い合わせの文脈、配備するカードの宿主、基本コスト（追加配備コストを含む）
     */
    private function costInputs(PartCall $call, int $cardNo, int $satelliteId, int $extra): array
    {
        $view = $call->ctx->view;
        $playerId = $call->host->playerId;
        $card = ProjectCards::get(sprintf('P%03d', $cardNo));
        $sat = $view->satelliteState($satelliteId);
        $active = $sat?->isActive() ?? false;
        $query = new QueryContext(
            $playerId,
            $satelliteId,
            $cardNo,
            $card->type,
            attachedCount: count($view->deployedCards($satelliteId)) + $extra,
        );
        $self = new EffectHost('P', $cardNo, $card->nameId, $playerId, $satelliteId, $sat?->shape, 0, $active, false);
        $surcharge = !empty($call->spec->params['surcharge']) && $active ? DeployPhase::ACTIVE_SURCHARGE : 0;

        return [$query, $self, $card->cost + $surcharge];
    }

    /**
     * 組ごとの配備の内訳。同じ衛星への 2 枚目以降は、先の配備でカード枚数が増えたものとして計算する。`useMods` の鍵は答えの順に、
     * それを使える最初の組に割り当てる。使えない鍵・重複があれば null。
     *
     * @param list<array{int, int}> $pairs
     * @return list<array{card: int, sat: int, cost: int, mods: list<string>}>|null
     */
    private function plan(PartCall $call, array $pairs, mixed $useMods): ?array
    {
        if (!is_array($useMods)) {
            return null;
        }
        $added = [];
        $assigned = array_fill(0, count($pairs), []);
        $options = [];
        foreach ($pairs as $i => [$cardNo, $satelliteId]) {
            $extra = $added[$satelliteId] ?? 0;
            $options[$i] = array_column($this->modOptions($call, $cardNo, $satelliteId, $extra), 'hostKey');
            $added[$satelliteId] = $extra + 1;
        }
        $seen = [];
        foreach ($useMods as $key) {
            if (!is_string($key) || isset($seen[$key])) {
                return null;
            }
            $seen[$key] = true;
            $placed = false;
            foreach ($pairs as $i => $_) {
                if (in_array($key, $options[$i], true)) {
                    $assigned[$i][] = $key;
                    $placed = true;
                    break;
                }
            }
            if (!$placed) {
                return null;
            }
        }
        $added = [];
        $plan = [];
        foreach ($pairs as $i => [$cardNo, $satelliteId]) {
            $extra = $added[$satelliteId] ?? 0;
            $plan[] = [
                'card' => $cardNo,
                'sat' => $satelliteId,
                'cost' => $this->cost($call, $cardNo, $satelliteId, $extra, $assigned[$i]),
                'mods' => $assigned[$i],
            ];
            $added[$satelliteId] = $extra + 1;
        }

        return $plan;
    }

    /**
     * @return list<array{int, int}>
     */
    private function pairsOf(Answer $answer): array
    {
        $pairs = [];
        foreach ((array) ($answer->fields['pairs'] ?? []) as $pair) {
            $pair = array_values((array) $pair);
            $pairs[] = [(int) ($pair[0] ?? 0), (int) ($pair[1] ?? 0)];
        }

        return $pairs;
    }

    /**
     * @return list<int>
     */
    private function discardOf(Answer $answer): array
    {
        return array_values(array_map('intval', (array) ($answer->fields['discard'] ?? [])));
    }
}
