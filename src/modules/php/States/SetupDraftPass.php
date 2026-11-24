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
 * 全員の確定の後に、選んだカードを手札へ入れ、残りを席順の次の人へ渡す。4 回目の後の最後の 1 枚は、渡してから受け取った人の手札に入れる（013 §4.2）。
 */
class SetupDraftPass extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::SETUP_DRAFT_PASS,
            type: StateType::GAME,
            transitions: [
                'next' => StateId::SETUP_DRAFT_PICK,
                'done' => StateId::SETUP_FINISH,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->setup()->applyDraftPass();
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
