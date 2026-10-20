<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Game;

/**
 * 全員の手札を自動で 1 枚 3 マヤに換金する（P077・R009 の売却額増を適用。001 §10、Q-12、009 §3.9）。zombie のプレイヤーも同じ。
 */
class EndHandCashOut extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::END_HAND_CASH_OUT,
            type: StateType::GAME,
            transitions: [
                'scoring' => StateId::FINAL_SCORING,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->end()->cashOut();
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
