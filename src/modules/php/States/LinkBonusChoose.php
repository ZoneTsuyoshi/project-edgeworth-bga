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
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\projectedgeworth\Flow\Machine\ChoiceData;
use Bga\Games\projectedgeworth\Flow\Machine\ChooseState;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Game;

/**
 * IV 通信確立のボーナス: 対象区域が衛星の直下と進行方向の前後（001 §7-5）。それ以外はメインと同じ（009 §3.7）。zombie は [Pass]。
 */
class LinkBonusChoose extends ChooseState
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            StateId::LINK_BONUS_CHOOSE,
            clienttranslate('Other players must choose their Link bonus'),
            clienttranslate('${you} may establish another link (Action bonus), or pass'),
            StateId::LINK_DRIVER,
        );
    }

    /**
     * @param string $op link / pass（キューブの放棄。Q-10）
     * @param list<int>|null $sell コストが足りないときに同梱する売却
     * @param list<string>|null $useMods 使う起動の修正の宿主の鍵（args の useMods の候補から。011 §6-3）
     */
    #[PossibleAction]
    #[CheckAction(false)]
    public function actConfirmLink(
        string $op,
        int $cell = 0,
        int $satId = 0,
        #[IntArrayParam] ?array $sell = null,
        #[JsonParam(alphanum: false)] ?array $useMods = null,
    ): void {
        $this->peConfirm((int) $this->game->getCurrentPlayerId(), ChoiceData::link($op, $cell, $satId, $sell, $useMods));
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
