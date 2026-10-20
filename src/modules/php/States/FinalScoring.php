<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\States;

use Bga\GameFramework\GameResult\GameResult;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Game;

/**
 * 名声 I〜IX を計算し（001 §12）、player_score に合計を保存して、内訳を通知する。同点は同順位（player_score_aux は使わない。Q-08）。
 * 99 ではなく GameResult::individualRanking を返してゲームを終える（009 B10）。
 */
class FinalScoring extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::FINAL_SCORING,
            type: StateType::GAME,
        );
    }

    public function onEnteringState(): GameResult
    {
        $outcome = $this->game->peMachine()->end()->score();
        foreach ($outcome->scores as $playerId => $fame) {
            $this->bga->playerScore->set($playerId, $fame, null);
        }
        $this->game->peStats->recordFinal($outcome);
        $this->game->peSend($outcome->notices);

        return GameResult::individualRanking();
    }
}
