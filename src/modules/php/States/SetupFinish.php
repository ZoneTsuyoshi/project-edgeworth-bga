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
 * セットアップの最後（009 §3.3）。round_no = 0 にして、全員が参加するセットアップの配備フェイズ（001 §5 手順 16）の準備をし、
 * 40 へ進める。
 */
class SetupFinish extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::SETUP_FINISH,
            type: StateType::GAME,
            transitions: [
                'start' => StateId::ACTION_PHASE_ROUTER,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->setup()->finish();
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
