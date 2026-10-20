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
 * V 企画: 引いたカードから残すカードを選ぶ（メイン 1 枚 / ボーナス 2 枚、効果による増減）（009 §3.7、008 §3-8）。引いたカードは取り消せない。
 * zombie は引いた順の先頭から必要な枚数を残す。
 */
class ProposalKeepChoose extends ChooseState
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            StateId::PROPOSAL_KEEP_CHOOSE,
            clienttranslate('Other players must choose cards to keep'),
            clienttranslate('${you} must choose cards to keep'),
            StateId::PROPOSAL_DRIVER,
        );
    }

    /**
     * 残すカード（引いたカードの ID）。残さないカードは捨て札になる。選び直しは全員の確定まで可。
     *
     * @param list<int>|null $keep
     */
    #[PossibleAction]
    #[CheckAction(false)]
    public function actConfirmKeep(#[IntArrayParam] ?array $keep): void
    {
        $this->peConfirm((int) $this->game->getCurrentPlayerId(), ['keep' => ChoiceData::ids($keep)]);
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
