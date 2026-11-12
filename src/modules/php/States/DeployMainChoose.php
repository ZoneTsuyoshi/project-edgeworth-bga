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
 * II 配備: 手札のカード 1 枚を配備先の衛星に配備する、または [Pass]。配備のボーナス保持者は同時に、追加の配備または 1 枚引くも選ぶ（009 §3.7、008 §3-6・§3-7、作者回答 2026-10-20）。コスト不足の売却は選択に同梱できる。
 * 追加の配備は [Deploy another card]（actStageDeploy）で 1 枚目を先に確定（ステージ）してから 2 枚目を選ぶ。ステージは [Reset]（actResetStage）で取り消せる
 * （山札から引いた結果が出るまで。009 追補 2026-11-12）。zombie は [Pass]。ステージ済みなら 1 枚目を残して 2 枚目なしで確定。
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
     * 確定。未ステージ（S0）は op / card / sat と、ボーナス保持者の extra {"op": "draw"}。ステージ済み（S1）は op と 1 枚目の引数を
     * 無視して、extra（2 枚目 {"op": "deploy", "card", "sat", "sell", "useMods"}、または {"op": "pass"}）だけを検証・保存する。
     *
     * @param string $op deploy / pass
     * @param list<int>|null $sell 支払い不足のときに同梱する売却（その配備のカードのコストに対する売却）
     * @param list<string>|null $useMods 配備に使う起動の修正の宿主の鍵（args の useMods の候補から。011 §6-3）
     * @param array<string, mixed>|null $extra ボーナス保持者の追加の行動
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
     * [Deploy another card]: 1 枚目を先に確定（ステージ）する（S0 → S1）。ボーナス保持者だけ。確定済みでも呼べる（確定は取り消して S1 の
     * 未確定に戻す）。失敗は状態を変えない。
     *
     * @param list<int>|null $sell 1 枚目のコスト不足のときに同梱する売却
     * @param list<string>|null $useMods 1 枚目に使う起動の修正の宿主の鍵
     */
    #[PossibleAction]
    #[CheckAction(false)]
    public function actStageDeploy(
        int $cardId,
        int $satId,
        #[IntArrayParam] ?array $sell = null,
        #[JsonParam(alphanum: false)] ?array $useMods = null,
    ): void {
        $playerId = (int) $this->game->getCurrentPlayerId();
        $data = ChoiceData::deploy('deploy', $cardId, $satId, $sell, $useMods);
        $this->peStage($playerId, fn() => $this->game->peMachine()->deployStage()->stage($playerId, $data));
    }

    /**
     * [Reset]: ステージした 1 枚目を取り消して S0 に戻る。山札から引いた結果が出ていれば拒否。
     */
    #[PossibleAction]
    #[CheckAction(false)]
    public function actResetStage(): void
    {
        $playerId = (int) $this->game->getCurrentPlayerId();
        $this->peStage($playerId, fn() => $this->game->peMachine()->deployStage()->reset($playerId));
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
