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
 * 現状は最小構成（Studio でゲームを作成して開始できる骨格）。状態機械（009）は I-07 で States/ に実装し、
 * 今の仮の状態（States/PlaceholderWait・PlaceholderEnd）と置き換える。
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth;

use Bga\Games\projectedgeworth\Board\BgaRandom;
use Bga\Games\projectedgeworth\Data\BgaDatabase;
use Bga\Games\projectedgeworth\Data\Database;
use Bga\Games\projectedgeworth\Data\GlobalValues;
use Bga\Games\projectedgeworth\Data\LinkRepository;
use Bga\Games\projectedgeworth\Data\PlayerValues;
use Bga\Games\projectedgeworth\Data\SatelliteRepository;
use Bga\Games\projectedgeworth\Flow\EndCondition;
use Bga\Games\projectedgeworth\Flow\PlayerProgress;
use Bga\Games\projectedgeworth\Setup\GameOptions;
use Bga\Games\projectedgeworth\Setup\GameSetup;
use Bga\Games\projectedgeworth\Setup\SetupPlayer;
use Bga\Games\projectedgeworth\States\PlaceholderWait;

class Game extends \Bga\GameFramework\Table
{
    public Database $peDatabase;
    public GlobalValues $peGlobals;
    public PlayerValues $pePlayers;

    public function __construct()
    {
        parent::__construct();

        $this->peDatabase = new BgaDatabase();
        $this->peGlobals = new GlobalValues($this->bga->globals);
        $this->pePlayers = new PlayerValues($this->peDatabase);
    }

    /**
     * ゲーム進行度 0〜99（009 §10）。単調増加にするため、記録した最高値を下回らない。
     */
    public function getGameProgression()
    {
        $satellites = new SatelliteRepository($this->peDatabase);
        $links = new LinkRepository($this->peDatabase);
        $progress = [];
        foreach ($this->pePlayers->playerIds() as $playerId) {
            $active = count(array_filter(
                $satellites->forPlayer($playerId),
                static fn($satellite): bool => $satellite->isActive(),
            ));
            $progress[] = new PlayerProgress($playerId, $active, $links->countFor($playerId));
        }
        // 最高値の記録（progress_high_water）の更新は状態の側（I-07）で行う。ここは読むだけ
        return EndCondition::progress($progress, $this->peGlobals->progressHighWater());
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
     * 閲覧者に見せてよい情報だけを返す。現状は最小（プレイヤーのマヤと予算）。盤面・カードは I-10 で足す。
     */
    protected function getAllDatas(int $currentPlayerId): array
    {
        $result = [];

        $result['players'] = $this->getCollectionFromDb(
            'SELECT `player_id` AS `id`, `player_score` AS `score` FROM `player`',
        );
        foreach ($this->pePlayers->publicValues() as $playerId => $values) {
            $result['players'][$playerId]['maya'] = $values['maya'];
            $result['players'][$playerId]['budget'] = $values['budget'];
        }

        return $result;
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

        // I-07 で SetupLaunchPadNext（10）に置き換える
        return PlaceholderWait::class;
    }

    /**
     * デバッグ用: 指定した状態へ飛ぶ（Studio の Debug ボタンから）。
     */
    public function debug_goToState(int $state = 8)
    {
        $this->gamestate->jumpToState($state);
    }
}
