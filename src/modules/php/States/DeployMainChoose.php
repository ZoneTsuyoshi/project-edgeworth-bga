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
 * II 配備: 手札のカード 1 枚を配備先の衛星に配備する、または [Pass]。配備のボーナス保持者は同時に、追加の配備または 1 枚引くも選ぶ（009 §3.7、008 §3-6・§3-7、作者回答 2026-10-20）。コスト不足の売却は選択に同梱できる。zombie は [Pass]。
 */
class DeployMainChoose extends ChooseState
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            StateId::DEPLOY_MAIN_CHOOSE,
            clienttranslate('Other players must choose their deployment'),
            clienttranslate('${you} must deploy a card to a satellite, or pass'),
            StateId::DEPLOY_DRIVER,
        );
    }

    /**
     * @param string $op deploy / pass
     * @param list<int>|null $sell 支払い不足のときに同梱する売却（通常の配備と追加の配備の合計コストに対する売却）
     * @param list<string>|null $useMods 通常の配備に使う起動の修正の宿主の鍵（args の useMods の候補から。011 §6-3）
     * @param array<string, mixed>|null $extra ボーナス保持者の追加の行動: {"op": "deploy", "card", "sat", "useMods"} / {"op": "draw"}
     */
    #[PossibleAction]
    #[CheckAction(false)]
    public function actConfirmDeploy(
        string $op,
        int $cardId = 0,
        int $satId = 0,
        #[IntArrayParam] ?array $sell = null,
        #[JsonParam(alphanum: false)] ?array $useMods = null,
        #[JsonParam(alphanum: false)] ?array $extra = null,
    ): void {
        $this->peConfirm((int) $this->game->getCurrentPlayerId(), ChoiceData::deploy($op, $cardId, $satId, $sell, $useMods, $extra));
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
