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
 * 全員の確定の後に、捨てるカードを公開して捨て札に置き、席順（基準プレイヤーから）に同じ枚数を引き直す（001 U-4、008 §8-1、009 §3.3）。
 */
class SetupMulliganApply extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::SETUP_MULLIGAN_APPLY,
            type: StateType::GAME,
            transitions: [
                'done' => StateId::SETUP_FINISH,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->setup()->applyMulligan();
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
