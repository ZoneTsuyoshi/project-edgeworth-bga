<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Result;

use Bga\Games\projectedgeworth\Effects\Context\GameEvent;
use Bga\Games\projectedgeworth\Effects\LogEntry;
use Bga\Games\projectedgeworth\Effects\Outcome;
use Bga\Games\projectedgeworth\Effects\Parts\AbstractResultPart;
use Bga\Games\projectedgeworth\Effects\Parts\PartCall;
use Bga\Games\projectedgeworth\Effects\Query\EffectQueries;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Effects\Question\Answer;
use Bga\Games\projectedgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\ProjectCards;

/**
 * R10 `DEPLOY_DECK_TOP`（P035）: このカードを捨て、代わりに山札の一番上を配備コスト（追加配備コストも）なしでこの衛星に配備する（強制）。
 * 山札が空なら捨て札をシャッフルして山札にする（P035 自身を捨てた後なので、捨て札に入っている）。
 *
 * 新しく配備したカードの `ON_DEPLOY` と `ON_LAUNCH` が発動し（FAQ 行 16）、他のカードの「配備したとき」の反応効果も働く
 * （配備されたそのカード自身は反応しない）。そのため `DEPLOYED` と、`cardId` つきの `LAUNCHED`（そのカードの打ち上げ時効果だけを起こす）を返す。
 * 燃料庫の数が変わるので、c は `FuelAdjust` で直す（N-1）。
 * この衛星に配備できない（F01 `DEPLOY_TO_THIS`、P033）ときは、このカードを捨てずに何も起こさない（「代わりに」配備できないため。解釈）。
 */
final class DeployDeckTopPart extends AbstractResultPart
{
    public function __construct(private readonly EffectQueries $queries)
    {
    }

    public function isAvailable(PartCall $call): bool
    {
        $satelliteId = $call->host->satelliteId;

        return $satelliteId !== null
            && $this->queries->allowed(new QueryContext($call->host->playerId, $satelliteId, action: ForbiddenAction::DeployTo));
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $playerId = $call->host->playerId;
        $satelliteId = (int) $call->host->satelliteId;
        $discardSelf = !empty($call->spec->params['discardSelf']);
        $cardNo = FuelAdjust::around($call->ctx, $satelliteId, function () use ($call, $playerId, $satelliteId, $discardSelf): ?int {
            if ($discardSelf && $call->host->kind === 'P') {
                $call->ctx->mutator->discardDeployed($call->host->src);
            }

            return $call->ctx->mutator->deployFromDeck($playerId, $satelliteId);
        });
        $logs = [];
        if ($discardSelf) {
            $logs[] = new LogEntry('cardDiscarded', ['player_id' => $playerId, 'card_key' => $call->spec->cardKey, 'card_id' => $call->host->src]);
        }
        if ($cardNo === null) {
            return new Outcome([], $logs);
        }
        $type = ProjectCards::get(sprintf('P%03d', $cardNo))->type;
        $logs[] = new LogEntry('cardDeployed', ['player_id' => $playerId, 'card_key' => $call->spec->cardKey, 'card_id' => $cardNo, 'sat_id' => $satelliteId, 'free' => true]);

        return new Outcome(
            [
                new GameEvent(Hook::Deployed, $playerId, $satelliteId, $cardNo, $type),
                new GameEvent(Hook::Launched, $playerId, $satelliteId, $cardNo, $type),
            ],
            $logs,
        );
    }
}
