<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts\Result;

use Bga\Games\ProjectEdgeworth\Effects\LogEntry;
use Bga\Games\ProjectEdgeworth\Effects\Outcome;
use Bga\Games\ProjectEdgeworth\Effects\Parts\AbstractResultPart;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartCall;
use Bga\Games\ProjectEdgeworth\Effects\Question\Answer;
use Bga\Games\ProjectEdgeworth\Effects\Question\Field;
use Bga\Games\ProjectEdgeworth\Effects\Question\FieldType;

/**
 * R04 `DISCARD_HAND`: 手札から捨て札へ捨てる（売却額は得ない。FAQ 行 70）。
 *
 * パラメータ: `count`（捨てる枚数）、`drawFirst`（P087: 1 段目に引く枚数。あれば 2 段の効果で、1 段目は強制で自動に引き、
 * 2 段目が手札から `count` 枚を捨てる問い）、`justDrawn`（zombie の既定は直前に引いたカード。未確定 3）。
 * 捨てる問いの `cards` は手札から選ぶ（直前に引いたカードを含む）。手札が `count` 枚に満たなければ手札の全部。手札が空なら問いなしで何もしない。
 * 1 段目で引いたカードは `PoolItem::$memo['drawn']` に残し、再接続しても zombie の既定が同じになる。
 */
final class DiscardHandPart extends AbstractResultPart
{
    public function isAvailable(PartCall $call): bool
    {
        return true;
    }

    public function buildFields(PartCall $call): array
    {
        if (!$this->isDiscardStage($call)) {
            return [];
        }
        $hand = $call->ctx->view->hand($call->host->playerId);
        $count = min($this->count($call), count($hand));

        return $count === 0 ? [] : [new Field('cards', FieldType::Cards, $hand, $count, $count)];
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $playerId = $call->host->playerId;
        if (!$this->isDiscardStage($call)) {
            $before = $call->ctx->view->hand($playerId);
            $drawn = $call->ctx->mutator->drawCards($playerId, (int) $call->spec->params['drawFirst']);
            $call->item->memo['drawn'] = array_values(array_diff($call->ctx->view->hand($playerId), $before));

            return new Outcome([], [new LogEntry('cardsDrawn', ['player_id' => $playerId, 'card_key' => $call->spec->cardKey, 'count' => $drawn])]);
        }
        $cards = array_values(array_map('intval', (array) ($answer?->fields['cards'] ?? [])));
        if ($cards === []) {
            return Outcome::none();
        }
        $call->ctx->mutator->discardFromHand($playerId, $cards);

        return new Outcome([], [new LogEntry('cardsDiscarded', ['player_id' => $playerId, 'card_key' => $call->spec->cardKey, 'cards' => $cards])]);
    }

    public function defaultAnswer(PartCall $call): ?Answer
    {
        if (!$this->isDiscardStage($call)) {
            return null;
        }
        $hand = $call->ctx->view->hand($call->host->playerId);
        if ($hand === []) {
            return null;
        }
        $drawn = array_values(array_filter(
            array_map('intval', (array) ($call->item->memo['drawn'] ?? [])),
            static fn(int $id): bool => in_array($id, $hand, true),
        ));
        $pick = $drawn !== [] ? array_slice($drawn, 0, $this->count($call)) : [];
        if (count($pick) < min($this->count($call), count($hand))) {
            $rest = array_values(array_diff($hand, $pick));
            sort($rest);
            $pick = [...$pick, ...array_slice($rest, 0, min($this->count($call), count($hand)) - count($pick))];
        }

        return new Answer(['cards' => $pick]);
    }

    public function preview(PartCall $call): array
    {
        return ['discard' => $this->count($call)];
    }

    private function count(PartCall $call): int
    {
        return (int) ($call->spec->params['count'] ?? 1);
    }

    /** 捨てる段か（`drawFirst` があれば 2 段目、なければ 1 段目） */
    private function isDiscardStage(PartCall $call): bool
    {
        return $call->stage === (isset($call->spec->params['drawFirst']) ? 2 : 1);
    }
}
