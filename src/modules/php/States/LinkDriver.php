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
 * IV 通信確立 のドライバ（009 §3.7）。窓 XD → メイン → コミット（世代 0）→ 窓 KE → ボーナス → コミット → 窓 KE。
 * ボーナス保持者がいなければボーナスの選択状態に入らない。
 */
class LinkDriver extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::LINK_DRIVER,
            type: StateType::GAME,
            transitions: [
                'main' => StateId::LINK_MAIN_CHOOSE,
                'bonus' => StateId::LINK_BONUS_CHOOSE,
                'window' => StateId::EFFECT_WINDOW_OPEN,
                'next' => StateId::ACTION_PHASE_ROUTER,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->drivers()->action(4);
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
