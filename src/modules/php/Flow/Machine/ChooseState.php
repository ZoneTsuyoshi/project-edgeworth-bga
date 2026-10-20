<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\UserException;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Game;

/**
 * 同時選択の状態（13・22・61・62・71・72・81・82・91）の共通部分（009 §5）。全員が同時に選び、確定は何度でも上書きでき、
 * 全員が確定したら 'allConfirmed' で遷移する。具体的な状態クラスは States/ に置き、コンストラクタと act* だけを書く。
 * BGA は States/ の中の全クラスを状態として読むので、この基底クラスは States/ に置かない。
 *
 * 案 A / 案 B の違いは ConfirmMode::CURRENT の定数 1 つ（Game::peAfterConfirm が読む）。
 */
abstract class ChooseState extends GameState
{
    protected Game $game;

    public function __construct(Game $game, int $id, string $description, string $descriptionMyTurn, int $nextStateId)
    {
        $this->game = $game;
        parent::__construct(
            $game,
            id: $id,
            type: StateType::MULTIPLE_ACTIVE_PLAYER,
            description: $description,
            descriptionMyTurn: $descriptionMyTurn,
            transitions: ['allConfirmed' => $nextStateId],
        );
    }

    /**
     * 参加者を決めて、選べる人だけをアクティブにする。行動できない人は [Pass] 相当を確定済みにする。
     * 選べる人がいなければ、setPlayersMultiactive が即座に 'allConfirmed' で遷移する（B7）。
     */
    public function onEnteringState(): void
    {
        [$entry, $notices] = $this->game->peMachine()->choice($this->id)->enter();
        $this->game->peSend($notices);
        $this->game->gamestate->setPlayersMultiactive($entry->active, 'allConfirmed', true);
    }

    /**
     * 公開部分は参加者と確定済みの ID だけ。本人の確定内容と候補は _private。DB は書かない（010 §13）。
     *
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return $this->game->peMachine()->choice($this->id)->args();
    }

    /**
     * 離脱したプレイヤーは既定の選択を確定して、非ブロッキングにする（009 §9）。
     */
    public function zombie(int $playerId): void
    {
        $result = $this->game->peMachine()->choice($this->id)->zombie($playerId);
        $this->game->peSend($result->notices);
        $this->game->peAfterConfirm($playerId, $result, true);
    }

    /**
     * 確定（act* から呼ぶ）。選び直しは確定のたびに上書きする。
     *
     * @param array<mixed> $data
     */
    protected function peConfirm(int $playerId, array $data): void
    {
        try {
            $result = $this->game->peMachine()->choice($this->id)->confirm($playerId, $data);
        } catch (RuleViolation $violation) {
            throw new UserException($this->game->_($violation->getMessage()));
        }
        $this->game->giveExtraTime($playerId);
        $this->game->peSend($result->notices);
        $this->game->peAfterConfirm($playerId, $result);
    }

    /**
     * 売却（actSell から呼ぶ。009 §8）。
     *
     * @param list<int>|null $cardIds
     */
    protected function peSell(int $playerId, ?array $cardIds): void
    {
        try {
            $notices = $this->game->peMachine()->sell($playerId, ChoiceData::ids($cardIds), $this->id);
        } catch (RuleViolation $violation) {
            throw new UserException($this->game->_($violation->getMessage()));
        }
        $this->game->peSend($notices);
    }
}
