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
 * IV 通信確立のメイン: 通信確立する区域（と衛星）、または [Pass]（キューブの放棄。Q-10）（009 §3.7、008 §3-4）。zombie は [Pass]。
 */
class LinkMainChoose extends ChooseState
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            StateId::LINK_MAIN_CHOOSE,
            clienttranslate('Other players must establish a link or pass'),
            clienttranslate('${you} must choose an area to establish a link, or pass'),
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
