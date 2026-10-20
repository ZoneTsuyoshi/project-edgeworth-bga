<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Setup;

use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Data\GlobalValues;
use Bga\Games\projectedgeworth\Material\SatelliteColor;
use Bga\Games\projectedgeworth\Setup\GameOptions;
use Bga\Games\projectedgeworth\Setup\GameSetup;
use Bga\Games\projectedgeworth\Setup\SetupPlayer;
use ProjectEdgeworthTests\Support\ArrayGlobals;
use ProjectEdgeworthTests\Support\DatabaseTestCase;
use ProjectEdgeworthTests\Support\Schema;
use ProjectEdgeworthTests\Support\SeededRandom;

/**
 * セットアップの結合テストの共通部品。プレイヤー ID は 101 から席順に振る。
 */
abstract class SetupTestCase extends DatabaseTestCase
{
    protected GlobalValues $globals;

    /** @var list<SetupPlayer> */
    protected array $players = [];

    /**
     * @param array<int, int> $optionValues ゲームオプション ID => 値
     */
    protected function runSetup(int $playerCount, array $optionValues = [], int $seed = 1): BoardLayout
    {
        Schema::reset($this->db);
        $colors = [SatelliteColor::Red, SatelliteColor::Green, SatelliteColor::Blue, SatelliteColor::Yellow];
        $ids = [];
        $this->players = [];
        for ($i = 0; $i < $playerCount; $i++) {
            $ids[] = 101 + $i;
            $this->players[] = new SetupPlayer(101 + $i, $colors[$i]);
        }
        $this->addPlayers($ids);
        $this->globals = new GlobalValues(new ArrayGlobals());
        $options = GameOptions::fromTableOptions($playerCount, static fn(int $id): ?int => $optionValues[$id] ?? null);
        return (new GameSetup($this->db, $this->globals, new SeededRandom($seed)))->run($this->players, $options);
    }

    /**
     * DB とグローバル値の全内容を 1 つの配列にする（再現性の比較用）。
     *
     * @return array<string, mixed>
     */
    protected function snapshot(): array
    {
        return [
            'cards' => $this->db->rows('SELECT * FROM `project_card` ORDER BY `card_id`'),
            'regions' => $this->db->rows('SELECT * FROM `region` ORDER BY `region_br`, `region_bc`'),
            'missions' => $this->db->rows('SELECT * FROM `mission_card` ORDER BY `mission_no`'),
            'players' => $this->db->rows('SELECT * FROM `player` ORDER BY `player_no`'),
            'rewards' => $this->globals->rewardCards(),
            'cols' => $this->globals->boardCols(),
            'shift' => $this->globals->rowShift(),
            'face' => $this->globals->boardFace()->value,
        ];
    }
}
