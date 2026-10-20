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
 * I 予算発行（001 §7-2、007 §4、009 §3.6）。プレイヤーが直接選ぶ選択状態はない。窓 B0・XD・B2・B3 の位置で、効果があれば窓（30）へ出る。
 */
class BudgetDriver extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::BUDGET_DRIVER,
            type: StateType::GAME,
            transitions: [
                'window' => StateId::EFFECT_WINDOW_OPEN,
                'next' => StateId::ACTION_PHASE_ROUTER,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->drivers()->budget();
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
