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
 * V 企画のドライバ（009 §3.7）。窓 XD → 全員がカードを引く → 残すカードの選択（91）→ 残す以外を捨てる → 窓 PB。
 * V のボーナスはメインの代わりなので、選択は 1 回だけ。
 */
class ProposalDriver extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::PROPOSAL_DRIVER,
            type: StateType::GAME,
            transitions: [
                'choose' => StateId::PROPOSAL_KEEP_CHOOSE,
                'window' => StateId::EFFECT_WINDOW_OPEN,
                'next' => StateId::ACTION_PHASE_ROUTER,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $result = $this->game->peMachine()->drivers()->proposal();
        $this->game->peSend($result->notices);

        return $result->transition;
    }
}
