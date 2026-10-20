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
 * 発射拠点の配置順（基準プレイヤーから席順に 1 人ずつ。001 §5 手順 10、Q-18）で、次の未配置のプレイヤーをアクティブにする（009 §3.3）。
 * 全員配置済みなら 12 へ。
 */
class SetupLaunchPadNext extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::SETUP_LAUNCH_PAD_NEXT,
            type: StateType::GAME,
            transitions: [
                'place' => StateId::SETUP_LAUNCH_PAD_PLACE,
                'done' => StateId::SETUP_HAND_ROUTE,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $next = $this->game->peMachine()->setup()->nextPlacer();
        if ($next === null) {
            return 'done';
        }
        $this->game->gamestate->changeActivePlayer($next);

        return 'place';
    }
}
