<?php

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * projectedgeworth implementation : © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * PROJECT EDGEWORTH のメインクラス（薄い入口。仕様書 004 §5）。ゲームのロジックは Setup/・Flow/・Effects/ などにあり、
 * ここでは BGA のフレームワークとつなぐだけにする。独自のメソッドの名前は pe で始める（CLAUDE.md、仕様書 005 §2.10）。
 *
 * 状態機械（仕様書 009）は States/ の 30 状態。ルールの処理は Flow/Machine/ にあり、状態クラスはそれを呼んで通知を送るだけ。
 * ここには、フレームワークとつなぐ共通の部品（通知の送信、確定後のアクティブ状態の切り替え）と
 * getAllDatas・getGameProgression・setupNewGame を置く。
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth;

use Bga\Games\projectedgeworth\Board\BgaRandom;
use Bga\Games\projectedgeworth\Data\BgaDatabase;
use Bga\Games\projectedgeworth\Data\Database;
use Bga\Games\projectedgeworth\Data\GlobalValues;
use Bga\Games\projectedgeworth\Data\PlayerValues;
use Bga\Games\projectedgeworth\Flow\ConfirmAction;
use Bga\Games\projectedgeworth\Flow\ConfirmMode;
use Bga\Games\projectedgeworth\Flow\Machine\ConfirmResult;
use Bga\Games\projectedgeworth\Flow\Machine\FlowContext;
use Bga\Games\projectedgeworth\Flow\Machine\Machine;
use Bga\Games\projectedgeworth\Flow\Machine\Notice;
use Bga\Games\projectedgeworth\Flow\Machine\PublicView;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Setup\GameOptions;
use Bga\Games\projectedgeworth\Setup\GameSetup;
use Bga\Games\projectedgeworth\Setup\SetupPlayer;
use Bga\Games\projectedgeworth\States\SetupLaunchPadNext;

class Game extends \Bga\GameFramework\Table
{
    public Database $peDatabase;
    public GlobalValues $peGlobals;
    public PlayerValues $pePlayers;
    private ?Machine $peMachine = null;

    public function __construct()
    {
        parent::__construct();

        $this->peDatabase = new BgaDatabase();
        $this->peGlobals = new GlobalValues($this->bga->globals);
        $this->pePlayers = new PlayerValues($this->peDatabase);
    }

    /**
     * 状態機械のルール層への入口（Flow/Machine）。状態クラスはここから各フローを取り出す。
     *
     * 効果エンジンの本番結線（申し送り N-4・N-7）の差し込み口: FlowContext の WindowGate（窓を開くか）と ActionHooks
     * （コスト・候補への修正）に、効果エンジンの実装を渡す。今は効果なし（既定の NoEffectWindowGate・NoActionHooks）。
     */
    public function peMachine(): Machine
    {
        return $this->peMachine ??= new Machine(new FlowContext(
            $this->peDatabase,
            $this->peGlobals,
            new BgaRandom(),
            fn(int $playerId): string => $this->getPlayerNameById($playerId),
        ));
    }

    /**
     * 通知を送る（Notice の to が null なら全員、そうでなければ本人のみ。010 §10.1）。送信は状態クラスの処理が正常に終わった
     * リクエストの終わりにまとめて届く。
     *
     * @param list<Notice> $notices
     */
    public function peSend(array $notices): void
    {
        foreach ($notices as $notice) {
            if ($notice->to === null) {
                $this->bga->notify->all($notice->name, $notice->message, $notice->args);
            } else {
                $this->bga->notify->player($notice->to, $notice->name, $notice->message, $notice->args);
            }
        }
    }

    /**
     * 同時選択で 1 人が確定した後の、アクティブ状態の切り替え（009 §5.3）。案 A / 案 B の違いは ConfirmMode::CURRENT だけ。
     *
     * @param bool $zombie 離脱したプレイヤーの確定か（必ず非アクティブにして、ブロッキングを残さない）
     */
    public function peAfterConfirm(int $playerId, ConfirmResult $result, bool $zombie = false): void
    {
        $action = ConfirmMode::plan($result->first, $result->allConfirmed);
        if ($zombie && $action === ConfirmAction::None) {
            $action = ConfirmAction::DeactivatePlayer;
        }
        match ($action) {
            ConfirmAction::DeactivatePlayer => $this->gamestate->setPlayerNonMultiactive($playerId, 'allConfirmed'),
            ConfirmAction::DeactivateAll => $this->gamestate->setAllPlayersNonMultiactive('allConfirmed'),
            ConfirmAction::None => null,
        };
    }

    /**
     * ゲーム進行度 0〜99（009 §10）。単調増加にするため、記録した最高値を下回らない。
     * 状態 20 と 110 の updateGameProgression で呼ばれる。
     */
    public function getGameProgression()
    {
        return $this->peMachine()->end()->progression();
    }

    /**
     * DB スキーマの移行。公開前は不要（公開後に dbmodel.sql を変えたときに実装する）。
     *
     * @param int $from_version
     */
    public function upgradeTableDb($from_version)
    {
    }

    /**
     * 閲覧者に見せてよい情報だけを返す（010 §8）。非公開情報は本人の手札・引いた直後のカード・ミッションだけ。
     * 同時選択の確定内容と窓の問いは含めず、現在の状態の getArgs の _private が復元する。
     */
    protected function getAllDatas(int $currentPlayerId): array
    {
        $result = [];

        // フレームワーク用のキー players（プレイヤーごとの値は playerData）。maya・budget は I-10 のクライアントが移るまでの互換
        $result['players'] = $this->getCollectionFromDb(
            'SELECT `player_id` AS `id`, `player_score` AS `score` FROM `player`',
        );
        foreach ($this->pePlayers->publicValues() as $playerId => $values) {
            $result['players'][$playerId]['maya'] = $values['maya'];
            $result['players'][$playerId]['budget'] = $values['budget'];
        }

        return $result + (new PublicView($this->peMachine()->ctx))->build($currentPlayerId);
    }

    /**
     * ゲーム開始時に 1 回だけ呼ばれる。player 行を作り、GameSetup で DB を初期化する（001 §5）。
     *
     * @param array<int, array<string, mixed>> $players
     * @param array<mixed> $options
     */
    protected function setupNewGame($players, $options = [])
    {
        // 色はゲーム情報の player_colors の順（青・緑・赤・黄）で割り当てる。好みの色は後で反映される
        $gameinfos = $this->getGameinfos();
        $colors = $gameinfos['player_colors'];

        $values = [];
        foreach ($players as $playerId => $player) {
            $values[] = vsprintf("(%s, '%s', '%s')", [
                $playerId,
                array_shift($colors),
                addslashes((string) $player['player_name']),
            ]);
        }
        static::DbQuery(
            sprintf('INSERT INTO `player` (`player_id`, `player_color`, `player_name`) VALUES %s', implode(',', $values)),
        );

        $this->reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        $this->reloadPlayersBasicInfos();

        // 反映後の色を読み、席順（$players の順）の SetupPlayer を作る
        $colorById = $this->getCollectionFromDb('SELECT `player_id`, `player_color` FROM `player`', true);
        $setupPlayers = [];
        foreach (array_keys($players) as $playerId) {
            $setupPlayers[] = SetupPlayer::fromBgaColor((int) $playerId, (string) $colorById[$playerId]);
        }

        $gameOptions = GameOptions::fromTableOptions(
            count($setupPlayers),
            fn(int $optionId): ?int => $this->bga->tableOptions->get($optionId),
        );
        (new GameSetup($this->peDatabase, $this->peGlobals, new BgaRandom()))->run($setupPlayers, $gameOptions);

        $this->activeNextPlayer();

        return SetupLaunchPadNext::class;
    }

    /**
     * デバッグ用: 指定した状態へ飛ぶ（Studio の Debug ボタンから）。
     */
    public function debug_goToState(int $state = StateId::ROUND_START)
    {
        $this->gamestate->jumpToState($state);
    }

    /**
     * デバッグ用: 効果の窓（30 → 32）を、今いる状態を戻り先にして素通りさせる。窓の戻り先に整数の状態 ID を返して遷移できること
     * （009 V-4）と、状態 ID が 100 以上でも動くこと（V-3）の確認用。戻った状態はもう一度入場処理が走る。
     */
    public function debug_testWindow(string $windowId = 'XD')
    {
        $this->peGlobals->setWindowId($windowId);
        $this->peGlobals->setWindowResume($this->gamestate->getCurrentMainStateId());
        $this->gamestate->jumpToState(StateId::EFFECT_WINDOW_OPEN);
    }

    /**
     * デバッグ用: 今の状態の zombie 処理を、指定したプレイヤーについて実行する（画面から操作できない間の動作確認用。
     * 同時選択の状態では既定の選択を確定して、確定後の非アクティブ化まで行う）。
     */
    public function debug_zombie(int $playerId)
    {
        $state = $this->gamestate->getCurrentMainState();
        if ($state === null) {
            throw new \Bga\GameFramework\SystemException('No current state');
        }
        $this->gamestate->runStateClassZombie($state, $playerId);
    }
}
