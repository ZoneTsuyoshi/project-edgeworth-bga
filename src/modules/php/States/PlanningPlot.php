<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\States;

use Bga\GameFramework\Actions\CheckAction;
use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\projectedgeworth\Flow\Machine\ChooseState;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Game;

/**
 * 全員が手札のアクションカード（横向きを除く）を 1 枚同時に選ぶ（009 §3.4、001 §6-1）。共通パターン（全員確定まで選び直し可。Q-09）。
 * zombie は I 予算発行。
 */
class PlanningPlot extends ChooseState
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            StateId::PLANNING_PLOT,
            clienttranslate('Other players must choose an action card'),
            clienttranslate('${you} must choose an action card'),
            StateId::PLANNING_REVEAL,
        );
    }

    #[PossibleAction]
    #[CheckAction(false)]
    public function actConfirmPlot(int $actionNo): void
    {
        $this->peConfirm((int) $this->game->getCurrentPlayerId(), ['action' => $actionNo]);
    }

    /**
     * @param list<int>|null $cardIds
     */
    #[PossibleAction]
    #[CheckAction(false)]
    public function actSell(#[IntArrayParam] ?array $cardIds): void
    {
        $this->peSell((int) $this->game->getCurrentPlayerId(), $cardIds);
    }
}
