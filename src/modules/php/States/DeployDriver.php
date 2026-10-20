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
 * II 配備 のドライバ（009 §3.7）。窓 XD（セットアップでは飛ばす）→ メイン → コミット → 窓 DE → ボーナス → コミット → 窓 DE。
 * ボーナス保持者がいなければボーナスの選択状態に入らない。
 */
class DeployDriver extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::DEPLOY_DRIVER,
            type: StateType::GAME,
            transitions: [
                'main' => StateId::DEPLOY_MAIN_CHOOSE,
                'bonus' => StateId::DEPLOY_BONUS_CHOOSE,
                'window' => StateId::EFFECT_WINDOW_OPEN,
                'next' => StateId::ACTION_PHASE_ROUTER,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->drivers()->action(2);
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
