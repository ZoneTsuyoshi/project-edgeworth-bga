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
 * 循環フェイズ（001 §9、009 §3.8）。移動 → 燃料消費 → ニアミス判定 → 着陸 / 着水判定。窓 O0・O1・O4 の位置で、効果があれば窓（30）へ出る。
 * 最後に着陸猶予を終わらせて 110 へ。
 */
class OrbitDriver extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::ORBIT_DRIVER,
            type: StateType::GAME,
            transitions: [
                'window' => StateId::EFFECT_WINDOW_OPEN,
                'next' => StateId::END_CHECK,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->orbit()->run();
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
