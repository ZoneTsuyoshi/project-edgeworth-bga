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
 * 選ばれたアクションフェイズを I → V の順に 1 つずつ進める（009 §3.6）。セットアップ（round_no = 0）は II 配備だけを行い、終わったら 20 へ。
 * 通常ラウンドで残りがなければ 100（循環）へ。
 */
class ActionPhaseRouter extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::ACTION_PHASE_ROUTER,
            type: StateType::GAME,
            transitions: [
                'budget' => StateId::BUDGET_DRIVER,
                'deploy' => StateId::DEPLOY_DRIVER,
                'launch' => StateId::LAUNCH_DRIVER,
                'link' => StateId::LINK_DRIVER,
                'proposal' => StateId::PROPOSAL_DRIVER,
                'orbit' => StateId::ORBIT_DRIVER,
                'round' => StateId::ROUND_START,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->rounds()->route();
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
