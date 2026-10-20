<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\States;

use Bga\GameFramework\Actions\CheckAction;
use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\projectedgeworth\Flow\Machine\ChoiceData;
use Bga\Games\projectedgeworth\Flow\Machine\ChooseState;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Game;

/**
 * II 配備のボーナス: [Deploy another card] / [Draw 1 card] / [Pass]（009 §3.7、008 §3-7）。セットアップでは全員が参加する。zombie は [Pass]。
 */
class DeployBonusChoose extends ChooseState
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            StateId::DEPLOY_BONUS_CHOOSE,
            clienttranslate('Other players must choose their Deploy bonus'),
            clienttranslate('${you} may deploy another card or draw 1 card (Action bonus)'),
            StateId::DEPLOY_DRIVER,
        );
    }

    /**
     * @param string $op deploy / draw（ボーナスのみ）/ pass
     * @param list<int>|null $sell 支払い不足のときに同梱する売却
     */
    #[PossibleAction]
    #[CheckAction(false)]
    public function actConfirmDeploy(
        string $op,
        int $cardId = 0,
        int $satId = 0,
        #[IntArrayParam] ?array $sell = null,
    ): void {
        $this->peConfirm((int) $this->game->getCurrentPlayerId(), ChoiceData::deploy($op, $cardId, $satId, $sell));
    }

    /**
     * @param list<int>|null $cardIds
     */
    #[PossibleAction]
    #[CheckAction(false)]
    public function actSell(#[IntArrayParam] ?array $cardIds): void
    {
        $this->peSell((int) $this->game->getCurrentPlayerId(), $cardIds);
    }
}
