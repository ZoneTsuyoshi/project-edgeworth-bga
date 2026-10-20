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
 * バリア（007 §1-2、009 §3.5、§6.3）。ステージされた通信確立を全員分一斉にコミットし（世代 +1）、起きた `LINKED` をプールに積んで、
 * 選択が要る人がいれば 31 へ戻る（'again'）。なければ窓を閉じ、保留通知を流して、window_resume（戻り先の状態 ID）を整数で返して遷移する（009 V-4）。
 */
class EffectWindowBarrier extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::EFFECT_WINDOW_BARRIER,
            type: StateType::GAME,
            transitions: [
                'again' => StateId::EFFECT_WINDOW_RESOLVE,
            ],
        );
    }

    public function onEnteringState(): int|string
    {
        $exit = $this->game->peMachine()->windows()->barrier();
        $this->game->peSend($exit->notices);

        return $exit->resume ?? 'again';
    }
}
