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
 * 効果の解決（全員並行。009 §3.5、§6.2）。選択が要る効果のあるプレイヤーだけがアクティブになり、各自が自分のプールを自分のペースで解決する。
 * 窓内の選択は確定した時点で解決され、取り消せない（007 §7-1、009 未確定 1）。
 *
 * 今回は効果エンジンを結線していないので、参加者がおらず、入場の直後に 'allDone' で抜ける。
 * 結線後に追加するもの（申し送り N-4）: getArgs の _private（現在の問いと選択肢）、actUseEffect / actSkipEffect / actOrderEffects
 * （#[CheckAction(false)] 不要。アクティブな人だけが答える）、zombie での辞退の繰り返し。
 */
class EffectWindowResolve extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::EFFECT_WINDOW_RESOLVE,
            type: StateType::MULTIPLE_ACTIVE_PLAYER,
            description: clienttranslate('Other players must resolve their card effects'),
            descriptionMyTurn: clienttranslate('${you} must resolve your card effects'),
            transitions: [
                'allDone' => StateId::EFFECT_WINDOW_BARRIER,
            ],
        );
    }

    public function onEnteringState(): void
    {
        $this->game->gamestate->setPlayersMultiactive($this->game->peMachine()->windows()->playersNeedingInput(), 'allDone', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return $this->game->peMachine()->windows()->resolveArgs();
    }

    /**
     * 離脱したプレイヤーは効果を辞退して非アクティブになる（009 §9.2）。辞退の繰り返しは効果エンジンの結線で追加する。
     */
    public function zombie(int $playerId): void
    {
        $this->game->gamestate->setPlayerNonMultiactive($playerId, 'allDone');
    }
}
