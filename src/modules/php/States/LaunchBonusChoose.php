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
 * III 打ち上げ・転回のボーナス: [Turn] / [Budget +1] / [Pass]（009 §3.7、008 §3-3）。zombie は [Pass]。
 */
class LaunchBonusChoose extends ChooseState
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            StateId::LAUNCH_BONUS_CHOOSE,
            clienttranslate('Other players must choose their Launch bonus'),
            clienttranslate('${you} may turn a satellite or raise your Budget by 1 (Action bonus)'),
            StateId::LAUNCH_DRIVER,
        );
    }

    /**
     * @param string $op launch（メインのみ）/ turn / budget（ボーナスのみ）/ pass
     * @param string $dir NE / E / SE / SW / W / NW
     * @param list<int>|null $sell 打ち上げコストが足りないときに同梱する売却
     */
    #[PossibleAction]
    #[CheckAction(false)]
    public function actConfirmLaunch(
        string $op,
        int $satId = 0,
        string $dir = '',
        #[IntArrayParam] ?array $sell = null,
    ): void {
        $this->peConfirm((int) $this->game->getCurrentPlayerId(), ChoiceData::launch($op, $satId, $dir, $sell));
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
