<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\States;

use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Machine\ChoiceData;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Game;

/**
 * 発射拠点の配置（001 §5 手順 10、009 §3.3）。空いている陸区域（まだ誰の発射拠点もない陸系の区域）から 1 つ選ぶ。
 * 配置順は逐次なので同時選択ではない。多段入力はクライアント状態で集めて 1 回送る。zombie は他の発射拠点から最も遠い区域に置く（§9.2）。
 */
class SetupLaunchPadPlace extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::SETUP_LAUNCH_PAD_PLACE,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must place their launch base'),
            descriptionMyTurn: clienttranslate('${you} must place your launch base on a land area'),
            transitions: [
                'placed' => StateId::SETUP_LAUNCH_PAD_NEXT,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return ['vacantCells' => $this->game->peMachine()->setup()->vacantCells()];
    }

    #[PossibleAction]
    public function actPlaceLaunchBase(int $cell, int $activePlayerId): string
    {
        try {
            $notices = $this->game->peMachine()->setup()->placeLaunchBase($activePlayerId, $cell);
        } catch (RuleViolation $violation) {
            throw new UserException($this->game->_($violation->getMessage()));
        }
        $this->game->giveExtraTime($activePlayerId);
        $this->game->peSend($notices);

        return 'placed';
    }

    /**
     * @param list<int>|null $cardIds
     */
    #[PossibleAction]
    public function actSell(#[IntArrayParam] ?array $cardIds, int $activePlayerId): void
    {
        try {
            $notices = $this->game->peMachine()->sell($activePlayerId, ChoiceData::ids($cardIds), StateId::SETUP_LAUNCH_PAD_PLACE);
        } catch (RuleViolation $violation) {
            throw new UserException($this->game->_($violation->getMessage()));
        }
        $this->game->peSend($notices);
    }

    public function zombie(int $playerId): string
    {
        $this->game->peSend($this->game->peMachine()->setup()->zombiePlace($playerId));

        return 'placed';
    }
}
