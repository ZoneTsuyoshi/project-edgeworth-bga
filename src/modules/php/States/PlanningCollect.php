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
 * アクションカードの回収（001 §6-1 の 1-1〜1-3。009 §3.4）。窓 PL1（P001・P002）を開く対象があれば窓へ（戻り先は 22）。
 */
class PlanningCollect extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::PLANNING_COLLECT,
            type: StateType::GAME,
            transitions: [
                'plot' => StateId::PLANNING_PLOT,
                'window' => StateId::EFFECT_WINDOW_OPEN,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->rounds()->collect();
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
