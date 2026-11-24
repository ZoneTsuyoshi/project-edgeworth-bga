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
use Bga\Games\projectedgeworth\Material\OptionId;
use Bga\Games\projectedgeworth\Setup\StartingHand;

/**
 * ゲームオプション（初期手札。001 §3-5）で分岐する（009 §3.3）。マリガン → 13、ドラフト → 15、なし → 17。
 */
class SetupHandRoute extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::SETUP_HAND_ROUTE,
            type: StateType::GAME,
            transitions: [
                'mulligan' => StateId::SETUP_MULLIGAN,
                'draft' => StateId::SETUP_DRAFT_PICK,
                'none' => StateId::SETUP_FINISH,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $option = StartingHand::tryFrom($this->bga->tableOptions->get(OptionId::STARTING_HAND) ?? StartingHand::None->value);

        return $this->game->peMachine()->setup()->route($option ?? StartingHand::None);
    }
}
