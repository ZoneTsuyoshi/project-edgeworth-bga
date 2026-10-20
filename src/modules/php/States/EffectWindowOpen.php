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
 * 効果の窓を開く（009 §3.5、§6.2）。window_id の窓にトリガーされた効果を集め、選択が要る人がいれば 31、いなければ 32 へ。
 */
class EffectWindowOpen extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::EFFECT_WINDOW_OPEN,
            type: StateType::GAME,
            transitions: [
                'choose' => StateId::EFFECT_WINDOW_RESOLVE,
                'barrier' => StateId::EFFECT_WINDOW_BARRIER,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->windows()->open();
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
