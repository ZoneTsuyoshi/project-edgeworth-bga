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
 * 終了判定（001 §10、009 §3.8）。着陸 / 着水判定の後に、(i) いずれかのプレイヤーの衛星が 5 機とも稼働中、(ii) いずれかのプレイヤーの
 * 通信確立数が 16、のどちらかを満たせば、このラウンドでゲームが終わる。満たしたプレイヤーを記録する（名声 IX）。進行度を更新する。
 */
class EndCheck extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::END_CHECK,
            type: StateType::GAME,
            updateGameProgression: true,
            transitions: [
                'end' => StateId::END_HAND_CASH_OUT,
                'nextRound' => StateId::ROUND_START,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->end()->check();
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
