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
 * ラウンドの開始（009 §3.4）。ラウンド番号を進め、起動効果の使用済みを全員分戻し、着陸猶予を終わらせ、ラウンド単位のデータを初期化する（§7）。
 * 進行度を更新する。
 */
class RoundStart extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::ROUND_START,
            type: StateType::GAME,
            updateGameProgression: true,
            transitions: [
                'plan' => StateId::PLANNING_COLLECT,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->rounds()->start();
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
