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
 * 全員が同時に、自分の前のカードから 1 枚を選ぶ（013 §4.1、009 §3.3）。共通パターン（選び直し可）。zombie は先頭のカード。
 */
class SetupDraftPick extends ChooseState
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            StateId::SETUP_DRAFT_PICK,
            clienttranslate('Other players must choose a card to keep (Draft)'),
            clienttranslate('${you} must choose a card to keep (Draft)'),
            StateId::SETUP_DRAFT_PASS,
        );
    }

    #[PossibleAction]
    #[CheckAction(false)]
    public function actConfirmDraftPick(int $card): void
    {
        $this->peConfirm((int) $this->game->getCurrentPlayerId(), ['card' => $card]);
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
