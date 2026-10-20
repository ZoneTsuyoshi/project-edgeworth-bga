<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\projectedgeworth\Game;

/**
 * 仮の待機状態（I-07 で 009 の状態機械に置き換える。ID 8 は 009 の状態一覧と重ならない番号）。
 * セットアップ直後にここへ入り、「End the game (temporary)」を押すとゲームを終了する。Studio で開始できることの確認用。
 */
class PlaceholderWait extends GameState
{
    public const ID = 8;

    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: self::ID,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must end the game (temporary state)'),
            descriptionMyTurn: clienttranslate('${you} must end the game (temporary state)'),
            updateGameProgression: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return [];
    }

    #[PossibleAction]
    public function actEndTemporarily(int $activePlayerId): string
    {
        $this->game->giveExtraTime($activePlayerId);
        return PlaceholderEnd::class;
    }

    public function zombie(int $playerId): string
    {
        return PlaceholderEnd::class;
    }
}
