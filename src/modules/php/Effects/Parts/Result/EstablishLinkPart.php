<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Result;

use Bga\Games\projectedgeworth\Board\Adjacency;
use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Data\LinkRepository;
use Bga\Games\projectedgeworth\Effects\Context\StagedLinkRequest;
use Bga\Games\projectedgeworth\Effects\EffectViolation;
use Bga\Games\projectedgeworth\Effects\LogEntry;
use Bga\Games\projectedgeworth\Effects\Outcome;
use Bga\Games\projectedgeworth\Effects\Parts\AbstractResultPart;
use Bga\Games\projectedgeworth\Effects\Parts\PartCall;
use Bga\Games\projectedgeworth\Effects\Query\EffectQueries;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Effects\Query\UseMods;
use Bga\Games\projectedgeworth\Effects\Question\Answer;
use Bga\Games\projectedgeworth\Effects\Question\Field;
use Bga\Games\projectedgeworth\Effects\Question\FieldType;
use Bga\Games\projectedgeworth\Effects\Question\ValidationResult;
use Bga\Games\projectedgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\projectedgeworth\Flow\Action\LinkCost;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Material\CostKind;
use Bga\Games\projectedgeworth\Material\Hook;

/**
 * R11 `ESTABLISH_LINK`: 効果による通信確立を**ステージに積む**（P047、P050、P051、P056〜P061、P076、P096〜P099。コミットは状態 32 のバリア。007 §7-3）。
 *
 * パラメータ（定義は `CardEffectsE6`）: `costKind`（`SPECIAL` = 通信確立コスト + 特殊コスト 5 / `NORMAL` = P076 だけ。FAQ 行 30）、
 * `actor`（`THIS` / `OTHER_OWN` / `CHOSEN_OWN`）、`area`（`BELOW` / `AHEAD` / `BEHIND` / `ADJACENT_LAND_OF_ACTOR`）。
 *
 * 対象区域（007 §5-4 の手順 1。通信確立のステップと同じ集合の作り方）:
 * 基準の区域に、衛星が持つ対象を広げる効果（M04）の和集合を常に足す（特殊通信確立・効果による通信確立にも効く。FAQ 行 23〜25）。
 * `BELOW` = 直下、`AHEAD` / `BEHIND` = 進行方向の前方 / 後方の隣接区域（反射を考慮しない。FAQ 行 26・27）、
 * `ADJACENT_LAND_OF_ACTOR` = 衛星の直下の区域に隣接する陸区域（P060・P061 は 011 未確定 1 の回答、P076 は印刷のとおり）。
 * ここから、自分のキューブ・発射拠点がある区域（001 §7-5 の (ii)）、同じ窓で自分がすでにステージした区域、通信確立できない衛星
 * （F01 `LINK_THIS`。FAQ 行 56）を除く。キューブが 16 個に達していれば（ステージの予約を含む）対象なし。
 *
 * 問い: 衛星が決まっていれば（`actor = THIS`）候補が複数のときだけ `area`、そうでなければ `pairs`（(衛星, 区域) の組。P050・P051・P076）、
 * 起動の修正があれば `useMods`（R021〜R025。011 §6-3）、P076 は `discard`（捨てる 2 枚。支払い C3）。候補が 1 つだけで修正もなければ
 * 問いは [Use] / [Skip] だけ。
 *
 * コスト（`LinkCost::compute()`）: 区域コスト + 先駆コストから修正を引き（区域コストから先、余りは先駆コストから席順）、特殊通信確立は下限 0 の後に +5。
 * 先駆コストはステージの時点でコミット済みのキューブだけを見る。同じバリアでステージされた他の通信確立は含まれないので、同じ世代の同じ区域は
 * 先駆コストなし、前の世代でコミットされた区域は先駆コストあり（007 §7-3）。所持金は同じ窓でステージした分を予約として差し引いて判定する
 * （支払いと先駆コストの受け取りはコミットが行う）。売却は [Use] の答えに同梱できる（エンジンがステージの前に実行する）。
 */
final class EstablishLinkPart extends AbstractResultPart
{
    public function __construct(
        private readonly EffectQueries $queries,
        private readonly UseMods $useMods,
        private readonly BoardLayout $layout,
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
        $fields = [];
        if (($call->spec->params['actor'] ?? 'THIS') === 'THIS') {
            $cells = array_values(array_unique(array_column($pairs, 1)));
            if (count($cells) > 1) {
                $fields[] = new Field('area', FieldType::Area, $cells, 1, 1);
            }
        } else {
            $fields[] = new Field('pairs', FieldType::Pairs, $pairs, 1, 1);
        }
        $keys = [];
        foreach ($pairs as [$satelliteId, $cell]) {
            foreach ($this->modOptions($call, $satelliteId, $cell) as $option) {
                $keys[$option['hostKey']] = true;
            }
        }
        if ($keys !== []) {
            $fields[] = new Field('useMods', FieldType::UseMods, array_keys($keys), 0, count($keys));
        }
        if ($this->discardCount($call) > 0) {
            $hand = $call->ctx->view->hand($call->host->playerId);
            $fields[] = new Field('discard', FieldType::Cards, $hand, $this->discardCount($call), $this->discardCount($call));
        }

        return $fields;
    }

    public function validate(PartCall $call, Answer $answer): ValidationResult
    {
        $pairs = $this->candidates($call);
        $chosen = $this->chosen($call, $answer, $pairs);
        if ($chosen === null) {
            return ValidationResult::error('select the area to link');
        }
        $discard = $this->discardOf($answer);
        if (
            count($discard) !== $this->discardCount($call)
            || array_intersect($answer->sell, $discard) !== []
        ) {
            return ValidationResult::error('a card cannot be discarded and sold at once');
        }
        $keys = $this->keysOf($call, $answer, $chosen);
        if ($keys === null) {
            return ValidationResult::error('invalid modifier to use');
        }
        $view = $call->ctx->view;
        $playerId = $call->host->playerId;
        $cost = $this->cost($call, $chosen[0], $chosen[1], $keys);
        if ($view->maya($playerId) + count($answer->sell) * $view->sellPrice($playerId) - $this->reserved($call) < $cost->total()) {
            return ValidationResult::error('not enough maya');
        }

        return ValidationResult::ok();
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $answer ??= new Answer();
        $chosen = $this->chosen($call, $answer, $this->candidates($call));
        $keys = $chosen === null ? null : $this->keysOf($call, $answer, $chosen);
        if ($chosen === null || $keys === null) {
            throw new EffectViolation('select the area to link');
        }
        [$satelliteId, $cell] = $chosen;
        $playerId = $call->host->playerId;
        $cost = $this->cost($call, $satelliteId, $cell, $keys);

        $discard = $this->discardOf($answer);
        if ($discard !== []) {
            $call->ctx->mutator->discardFromHand($playerId, $discard);
        }
        $this->useMods->commit($keys);

        return new Outcome(
            [],
            [new LogEntry('linkStaged', [
                'player_id' => $playerId,
                'card_key' => $call->spec->cardKey,
                'sat_id' => $satelliteId,
                'cell' => $cell,
                'special' => $cost->specialCost > 0,
                'cost' => $cost->total(),
            ])],
            [new StagedLinkRequest($playerId, $satelliteId, $cell, $call->host->key(), $cost->toArray())],
        );
    }

    public function preview(PartCall $call): array
    {
        $links = [];
        foreach ($this->candidates($call) as [$satelliteId, $cell]) {
            $keys = array_column($this->modOptions($call, $satelliteId, $cell), 'hostKey');
            $plain = $this->cost($call, $satelliteId, $cell, []);
            $links[] = [
                'sat' => $satelliteId,
                'cell' => $cell,
                'cost' => $plain->total(),
                'areaCost' => $plain->areaCost,
                'pioneer' => $plain->pioneer,
                'special' => $plain->specialCost,
                'costWithMods' => $this->cost($call, $satelliteId, $cell, $keys)->total(),
            ];
        }

        return ['links' => $links];
    }

    // ---- 内部 ----

    /** @return int 支払い C3 で捨てる枚数（P076 は 2。なければ 0） */
    private function discardCount(PartCall $call): int
    {
        return $call->spec->cost?->kind === CostKind::DiscardCost ? $call->spec->cost->amount : 0;
    }

    /**
     * 有効な (衛星 ID, 区域) の組（衛星 ID・区域の昇順）。最も安く払える使い方（使える起動の修正を全部使う）と、売却を使えば払えるもの。
     *
     * @return list<array{int, int}>
     */
    private function candidates(PartCall $call): array
    {
        $view = $call->ctx->view;
        $playerId = $call->host->playerId;
        $staged = $this->stagedOf($call);
        if (count($view->linkedCells($playerId)) + count($staged) >= LinkRepository::CUBES_PER_PLAYER) {
            return [];
        }
        $hand = count($view->hand($playerId));
        $discard = $this->discardCount($call);
        if ($hand < $discard) {
            return [];
        }
        $funds = $view->maya($playerId) - $this->reserved($call) + ($hand - $discard) * $view->sellPrice($playerId);
        $taken = array_column($staged, 'cell');

        $pairs = [];
        foreach ($this->actors($call) as $satelliteId) {
            foreach ($this->targets($call, $satelliteId) as $cell) {
                if (in_array($cell, $taken, true)) {
                    continue;
                }
                $keys = array_column($this->modOptions($call, $satelliteId, $cell), 'hostKey');
                if ($this->cost($call, $satelliteId, $cell, $keys)->total() <= $funds) {
                    $pairs[] = [$satelliteId, $cell];
                }
            }
        }

        return $pairs;
    }

    /**
     * @return list<int> 通信確立する衛星の候補（昇順。稼働中のもの）
     */
    private function actors(PartCall $call): array
    {
        $view = $call->ctx->view;
        $playerId = $call->host->playerId;
        $own = $call->host->satelliteId;
        $ids = match ($call->spec->params['actor'] ?? 'THIS') {
            'THIS' => $own === null ? [] : [$own],
            'OTHER_OWN' => array_values(array_filter($view->satelliteIds($playerId), static fn(int $id): bool => $id !== $own)),
            'CHOSEN_OWN' => $view->satelliteIds($playerId),
            default => throw new \LogicException('ESTABLISH_LINK needs actor THIS / OTHER_OWN / CHOSEN_OWN'),
        };
        $ids = array_values(array_filter(
            $ids,
            fn(int $id): bool => ($view->satelliteState($id)?->isActive() ?? false)
                && $this->queries->allowed(new QueryContext($playerId, $id, action: ForbiddenAction::Link)),
        ));
        sort($ids);

        return $ids;
    }

    /**
     * 衛星の対象区域（昇順）。自分のキューブ・発射拠点がある区域を除く。
     *
     * @return list<int>
     */
    private function targets(PartCall $call, int $satelliteId): array
    {
        $view = $call->ctx->view;
        $playerId = $call->host->playerId;
        $sat = $view->satelliteState($satelliteId);
        if ($sat === null || $sat->cell === null || $sat->direction === null) {
            return [];
        }
        $geometry = $this->layout->geometry();
        $here = $geometry->cellFromId($sat->cell);
        $base = [];
        switch ((string) ($call->spec->params['area'] ?? 'BELOW')) {
            case 'BELOW':
                $base = [$sat->cell];
                break;
            case 'AHEAD':
            case 'BEHIND':
                // 反射を考慮しない。盤面の上下の端を越える区域はない（左右はつながる）
                $sign = $call->spec->params['area'] === 'AHEAD' ? 1 : -1;
                $y = $here->y + $sign * $sat->direction->dy();
                if ($geometry->inRows($y)) {
                    $base = [$geometry->cellId($geometry->wrapX($here->x + $sign * $sat->direction->dx()), $y)];
                }
                break;
            case 'ADJACENT_LAND_OF_ACTOR':
                foreach (Adjacency::neighbors($geometry, $here->x, $here->y) as $near) {
                    $id = $geometry->cellId($near->x, $near->y);
                    if ($this->layout->areaType($id)->isLand()) {
                        $base[] = $id;
                    }
                }
                break;
            default:
                throw new \LogicException('ESTABLISH_LINK needs a known area');
        }
        // 通信確立の対象を広げる効果は、効果による通信確立にも常に効く（FAQ 行 23〜25）。ボーナス限定の拡張（R014）は IV のボーナスだけ
        $cells = $this->queries->linkTargets(
            new QueryContext($playerId, $satelliteId, cell: $sat->cell, mode: StepMode::Main, direction: $sat->direction),
            $base,
        );
        $cells = array_values(array_filter($cells, static fn(int $cell): bool => !$view->isOwnLinkedArea($playerId, $cell)));
        sort($cells);

        return $cells;
    }

    /**
     * @param list<string> $keys 使う起動の修正の宿主の鍵
     */
    private function cost(PartCall $call, int $satelliteId, int $cell, array $keys): LinkCost
    {
        $view = $call->ctx->view;
        $playerId = $call->host->playerId;
        $reef = LinkCost::reefDiscount($this->layout, $view->linkedCells($playerId), $cell);
        $waived = $this->queries->pioneerWaived(new QueryContext($playerId, $satelliteId));
        $recipients = $view->pioneerRecipients($playerId, $cell);
        $reduction = $this->queries->linkCost(new QueryContext($playerId, $satelliteId, cell: $cell), 0, $keys)->reduction();

        return LinkCost::compute($this->layout->areaType($cell), $reef, $reduction, $recipients, $waived, ($call->spec->params['costKind'] ?? 'SPECIAL') === 'SPECIAL');
    }

    /**
     * @return list<array{hostKey: string, cardKey: string, reduction: int}>
     */
    private function modOptions(PartCall $call, int $satelliteId, int $cell): array
    {
        return $this->useMods->options(Hook::LinkCost, new QueryContext($call->host->playerId, $satelliteId, cell: $cell));
    }

    /**
     * 同じ窓でステージした、自分の通信確立（まだコミットされていない予約）。
     *
     * @return list<array{sat: int, cell: int, total: int}>
     */
    private function stagedOf(PartCall $call): array
    {
        $list = [];
        foreach ($call->ctx->staged() as $staged) {
            if ($staged->playerId === $call->host->playerId) {
                $list[] = ['sat' => $staged->satelliteId, 'cell' => $staged->cell, 'total' => LinkCost::fromArray($staged->cost)->total()];
            }
        }

        return $list;
    }

    /** 同じ窓でステージした分の支払いの予約額 */
    private function reserved(PartCall $call): int
    {
        return array_sum(array_column($this->stagedOf($call), 'total'));
    }

    /**
     * 答えで選んだ (衛星 ID, 区域)。選択のフィールドがなければ（候補が 1 つ）その 1 つ。候補にないものは null。
     *
     * @param list<array{int, int}> $pairs
     * @return array{int, int}|null
     */
    private function chosen(PartCall $call, Answer $answer, array $pairs): ?array
    {
        if (($call->spec->params['actor'] ?? 'THIS') === 'THIS') {
            $cells = array_values(array_unique(array_column($pairs, 1)));
            $cell = $answer->fields['area'] ?? (count($cells) === 1 ? $cells[0] : null);
            $pick = $cell === null || $pairs === [] ? null : [$pairs[0][0], (int) $cell];
        } else {
            $given = array_values((array) (((array) ($answer->fields['pairs'] ?? []))[0] ?? []));
            $pick = count($given) === 2 ? [(int) $given[0], (int) $given[1]] : null;
        }
        if ($pick === null) {
            return null;
        }
        foreach ($pairs as $pair) {
            if ($pair === $pick) {
                return $pick;
            }
        }

        return null;
    }

    /**
     * 使う起動の修正の鍵（その通信確立で使えるもの。重複・使えない鍵があれば null）。
     *
     * @param array{int, int} $chosen
     * @return list<string>|null
     */
    private function keysOf(PartCall $call, Answer $answer, array $chosen): ?array
    {
        try {
            return $this->useMods->validate(
                array_values((array) ($answer->fields['useMods'] ?? [])),
                $this->modOptions($call, $chosen[0], $chosen[1]),
            );
        } catch (EffectViolation) {
            return null;
        }
    }

    /**
     * @return list<int>
     */
    private function discardOf(Answer $answer): array
    {
        return array_values(array_map('intval', (array) ($answer->fields['discard'] ?? [])));
    }
}
