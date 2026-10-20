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
 * III 打ち上げ・転回のメイン: [Launch]（衛星・向き）/ [Turn]（稼働中の衛星・向き）/ [Pass]（009 §3.7、008 §3-2）。zombie は [Pass]。
 */
class LaunchMainChoose extends ChooseState
{
    public function __construct(Game $game)
    {
        parent::__construct(
            $game,
            StateId::LAUNCH_MAIN_CHOOSE,
            clienttranslate('Other players must launch, turn or pass'),
            clienttranslate('${you} must launch a satellite, turn one, or pass'),
            StateId::LAUNCH_DRIVER,
        );
    }

    /**
     * @param string $op launch（メインのみ）/ turn / budget（ボーナスのみ）/ pass
     * @param string $dir NE / E / SE / SW / W / NW
     * @param list<int>|null $sell 打ち上げコストが足りないときに同梱する売却
     * @param list<string>|null $useMods 使う起動の修正の宿主の鍵（args の useMods の候補から。011 §6-3）
     */
    #[PossibleAction]
    #[CheckAction(false)]
    public function actConfirmLaunch(
        string $op,
        int $satId = 0,
        string $dir = '',
        #[IntArrayParam] ?array $sell = null,
        #[JsonParam(alphanum: false)] ?array $useMods = null,
    ): void {
        $this->peConfirm((int) $this->game->getCurrentPlayerId(), ChoiceData::launch($op, $satId, $dir, $sell, $useMods));
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
