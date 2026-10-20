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

/**
 * R15 `KEEP_PLOTTED_ACTIONS`: 計画フェイズの回収の手順 2 の直後（`PLANNING_RETRIEVE`）に、縦向きのアクションカードが `actions`
 * （アクション番号 I〜V = 1〜5。P001: 2・3、P002: 4・5）なら手札に戻す（横向きにしない）。
 * イベントは持ち主ごとに起こし、`data['upright']`（list<int>）に回収前の縦向きのカードを入れる。該当がなければ使えない（使用済みにならない）。
 */
final class KeepPlottedActionsPart extends AbstractResultPart
{
    public function isAvailable(PartCall $call): bool
    {
        return $this->targets($call) !== [];
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $kept = $this->targets($call);
        foreach ($kept as $actionNo) {
            $call->ctx->mutator->returnPlottedActionToHand($call->host->playerId, $actionNo);
        }

        return new Outcome([], [new LogEntry('actionCardsKept', ['player_id' => $call->host->playerId, 'card_key' => $call->spec->cardKey, 'actions' => $kept])]);
    }

    /**
     * @return list<int>
     */
    private function targets(PartCall $call): array
    {
        $upright = array_map('intval', (array) ($call->item->event->data['upright'] ?? []));
        $wanted = array_map('intval', (array) ($call->spec->params['actions'] ?? []));

        return array_values(array_intersect($wanted, $upright));
    }
}
