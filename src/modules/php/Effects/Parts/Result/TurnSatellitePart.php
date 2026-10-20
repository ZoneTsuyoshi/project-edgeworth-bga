<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Result;

use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Effects\Context\GameEvent;
use Bga\Games\projectedgeworth\Effects\LogEntry;
use Bga\Games\projectedgeworth\Effects\Outcome;
use Bga\Games\projectedgeworth\Effects\Parts\AbstractResultPart;
use Bga\Games\projectedgeworth\Effects\Parts\PartCall;
use Bga\Games\projectedgeworth\Effects\Query\EffectQueries;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Effects\Question\Answer;
use Bga\Games\projectedgeworth\Effects\Question\Field;
use Bga\Games\projectedgeworth\Effects\Question\FieldType;
use Bga\Games\projectedgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\projectedgeworth\Material\Hook;

/**
 * R08 `TURN_SATELLITE`: このカードのある衛星の向きを変える（P044・P048・P090・P095）。向きは 6 方向から選び、現在の向きも選べる
 * （向きを変えない 360 度の転回も `TURNED` を起こす。FAQ 行 17）。反射は転回ではない（このクラスは反射を扱わない）。
 * 稼働中の衛星だけが対象で、禁止 F01（`TURN_THIS`、P046）の衛星は使えない（`isAvailable()` が偽）。
 */
final class TurnSatellitePart extends AbstractResultPart
{
    public function __construct(private readonly EffectQueries $queries)
    {
    }

    public function isAvailable(PartCall $call): bool
    {
        $satelliteId = $call->host->satelliteId;
        $sat = $satelliteId === null ? null : $call->ctx->view->satelliteState($satelliteId);

        return $sat !== null
            && $sat->isActive()
            && $this->queries->allowed(new QueryContext($call->host->playerId, $sat->id, action: ForbiddenAction::Turn));
    }

    public function buildFields(PartCall $call): array
    {
        $directions = array_map(
            static fn(Direction $d): string => $d->value,
            [Direction::NE, Direction::E, Direction::SE, Direction::SW, Direction::W, Direction::NW], // 時計回り（右上から）
        );

        return [new Field('direction', FieldType::Direction, $directions)];
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $satelliteId = (int) $call->host->satelliteId;
        $sat = $call->ctx->view->satelliteState($satelliteId);
        $to = Direction::from((string) ($answer?->fields['direction'] ?? ''));
        if ($sat === null || !$sat->isActive()) {
            return Outcome::none();
        }
        $call->ctx->mutator->turnSatellite($satelliteId, $to);
        $playerId = $call->host->playerId;

        return new Outcome(
            [new GameEvent(Hook::Turned, $playerId, $satelliteId, cell: $sat->cell, data: ['from' => $sat->direction?->value, 'to' => $to->value])],
            [new LogEntry('satelliteTurned', [
                'player_id' => $playerId,
                'card_key' => $call->spec->cardKey,
                'sat_id' => $satelliteId,
                'direction' => $to->value,
            ])],
        );
    }
}
