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
 * 全員が同時に、捨てる枚数（0〜5 枚）を選ぶ（009 §3.3、008 §8-1）。共通パターン（選び直し可）。zombie は [Keep all]。
 */
class SetupMulligan extends ChooseState
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            StateId::SETUP_MULLIGAN,
            clienttranslate('Other players must choose cards to mulligan'),
            clienttranslate('${you} may discard cards to draw replacements (Mulligan)'),
            StateId::SETUP_MULLIGAN_APPLY,
        );
    }

    /**
     * 0 枚（[Keep all]）のときは空配列が送信で落ちて未指定になる（クライアントは discard を送らない）。未指定の null も空配列として扱う。
     *
     * @param list<int>|null $discard
     */
    #[PossibleAction]
    #[CheckAction(false)]
    public function actConfirmMulligan(#[IntArrayParam] ?array $discard = null): void
    {
        $this->peConfirm((int) $this->game->getCurrentPlayerId(), ['discard' => ChoiceData::ids($discard)]);
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
