<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\States;

use Bga\GameFramework\Actions\CheckAction;
use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\Actions\Types\JsonParam;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Machine\ChoiceData;
use Bga\Games\projectedgeworth\Flow\Machine\Notice;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Game;

/**
 * 効果の解決（全員並行。009 §3.5、§6.2）。選択が要る効果のあるプレイヤーだけがアクティブになり、各自が自分のプールを自分のペースで解決する。
 * 窓内の選択は確定した時点で解決され、取り消せない（007 §7-1、009 未確定 1）。
 *
 * getArgs の _private に現在の問い（`questions`）と選択肢、actUseEffect / actSkipEffect / actOrderEffects で答える。プールが空になった人から
 * 非アクティブになり、全員が済むと 'allDone' で 32 へ。zombie は問いを既定の答えで繰り返し、プールを空にする。
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
     * [Use]。取り消せない。
     *
     * @param array<string, mixed> $fields 問いのフィールドの key => 値（011 §6-1）
     * @param list<int>|null $sell 支払い不足のときに同梱する売却（008 §2-6）
     */
    #[PossibleAction]
    public function actUseEffect(int $entryId, #[JsonParam(alphanum: false)] array $fields = [], #[IntArrayParam] ?array $sell = null): void
    {
        $playerId = (int) $this->game->getCurrentPlayerId();
        $this->peAct($playerId, fn(): array => $this->game->peMachine()->windows()->use($playerId, $entryId, $fields, ChoiceData::ids($sell)));
    }

    /**
     * [Skip]。使用済みにならず、そのトリガーは失効する。
     */
    #[PossibleAction]
    public function actSkipEffect(int $entryId): void
    {
        $playerId = (int) $this->game->getCurrentPlayerId();
        $this->peAct($playerId, fn(): array => $this->game->peMachine()->windows()->skip($playerId, $entryId));
    }

    /**
     * 衝突する効果の順序の確定（集合の全要素を、解決したい順に並べた配列）。確定したら変えられない。
     *
     * @param list<int> $entryIds
     */
    #[PossibleAction]
    public function actOrderEffects(#[IntArrayParam] array $entryIds): void
    {
        $playerId = (int) $this->game->getCurrentPlayerId();
        $this->peAct($playerId, fn(): array => $this->game->peMachine()->windows()->order($playerId, ChoiceData::ids($entryIds)));
    }

    /**
     * 窓の中の売却（支払いのため。009 §8）。
     *
     * @param list<int>|null $cardIds
     */
    #[PossibleAction]
    #[CheckAction(false)]
    public function actSell(#[IntArrayParam] ?array $cardIds): void
    {
        $playerId = (int) $this->game->getCurrentPlayerId();
        try {
            $notices = $this->game->peMachine()->windows()->sell($playerId, ChoiceData::ids($cardIds));
        } catch (RuleViolation $violation) {
            throw new UserException($this->game->_($violation->getMessage()));
        }
        $this->game->peSend($notices);
    }

    /**
     * 離脱したプレイヤーは問いを既定の答えで繰り返し、プールを空にして非アクティブになる（009 §9.2）。
     */
    public function zombie(int $playerId): void
    {
        $this->game->peSend($this->game->peMachine()->windows()->zombie($playerId));
        $this->game->gamestate->setPlayerNonMultiactive($playerId, 'allDone');
    }

    /**
     * @param \Closure(): list<Notice> $operation
     */
    private function peAct(int $playerId, \Closure $operation): void
    {
        try {
            $notices = $operation();
        } catch (RuleViolation $violation) {
            throw new UserException($this->game->_($violation->getMessage()));
        }
        $this->game->giveExtraTime($playerId);
        $this->game->peSend($notices);
        if (!in_array($playerId, $this->game->peMachine()->windows()->playersNeedingInput(), true)) {
            $this->game->gamestate->setPlayerNonMultiactive($playerId, 'allDone');
        }
    }
}
