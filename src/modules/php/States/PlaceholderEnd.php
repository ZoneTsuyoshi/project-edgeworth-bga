<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\projectedgeworth\Game;

/**
 * 仮の終了状態（I-07 で EndScore（最終得点計算）などに置き換える）。BGA の終了状態（ID 99）へ進む。
 */
class PlaceholderEnd extends GameState
{
    public const ID = 9;
    /** BGA の予約済みの終了状態 */
    public const GAME_END = 99;

    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: self::ID,
            type: StateType::GAME,
        );
    }

    public function onEnteringState(): int
    {
        return self::GAME_END;
    }
}
