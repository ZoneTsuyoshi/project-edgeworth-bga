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
 * 全員のプロットを一斉に公開し、選ばれたアクションと、各アクションの選択者（ボーナス保持者）を確定する（009 §3.4）。
 * 窓 PL2（P003）を開く対象があれば窓へ（戻り先は 40）。
 */
class PlanningReveal extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::PLANNING_REVEAL,
            type: StateType::GAME,
            transitions: [
                'window' => StateId::EFFECT_WINDOW_OPEN,
                'next' => StateId::ACTION_PHASE_ROUTER,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->rounds()->reveal();
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
