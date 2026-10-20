<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts\Result;

use Bga\Games\ProjectEdgeworth\Effects\Context\GameEvent;
use Bga\Games\ProjectEdgeworth\Effects\LogEntry;
use Bga\Games\ProjectEdgeworth\Effects\Outcome;
use Bga\Games\ProjectEdgeworth\Effects\Parts\AbstractResultPart;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartCall;
use Bga\Games\ProjectEdgeworth\Effects\Question\Answer;
use Bga\Games\ProjectEdgeworth\Material\Hook;

/**
 * R03 `DRAW_CARDS`: プロジェクトカードを引く（山札が空なら捨て札をシャッフル。FAQ 行 3）。`count`、`countDevice`（この衛星の DEVICE の枚数。
 * P039。解決の時点で数える）。両方の山が空で引けない分は、何も起こさず解決したものとして扱う（007 エッジケース）。
 * 企画フェイズ中に 1 枚以上引いたときは `PROPOSAL_DRAWN` を起こす（P075。011 §6-5、007 未確定 11 の暫定案: 連鎖の引きも数える）。
 * 効果による引きには、企画の枚数修正（P067 など）は効かない（FAQ 行 29）。
 */
final class DrawCardsPart extends AbstractResultPart
{
    public function isAvailable(PartCall $call): bool
    {
        return ActionBonusFilter::allows($call);
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $params = $call->spec->params;
        $count = !empty($params['countDevice'])
            ? $call->ctx->view->deviceCountOn((int) $call->host->satelliteId)
            : (int) ($params['count'] ?? 0);
        $playerId = $call->host->playerId;
        $drawn = $count > 0 ? $call->ctx->mutator->drawCards($playerId, $count) : 0;

        $events = [];
        if ($drawn > 0 && $call->ctx->view->isProposalPhase()) {
            $events[] = new GameEvent(Hook::ProposalDrawn, $playerId, null, null, null, null, 0, ['count' => $drawn]);
        }

        return new Outcome($events, [new LogEntry('cardsDrawn', ['player_id' => $playerId, 'card_key' => $call->spec->cardKey, 'count' => $drawn])]);
    }
}
