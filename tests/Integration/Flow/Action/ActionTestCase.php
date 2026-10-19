<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Action;

use Bga\Games\ProjectEdgeworth\Board\AreaType;
use Bga\Games\ProjectEdgeworth\Board\BoardLayout;
use Bga\Games\ProjectEdgeworth\Board\Direction;
use Bga\Games\ProjectEdgeworth\Data\CardLocation;
use Bga\Games\ProjectEdgeworth\Data\GlobalValues;
use Bga\Games\ProjectEdgeworth\Data\Satellite;
use Bga\Games\ProjectEdgeworth\Flow\Action\ActionPhases;
use Bga\Games\ProjectEdgeworth\Material\OptionId;
use Bga\Games\ProjectEdgeworth\Material\SatelliteColor;
use Bga\Games\ProjectEdgeworth\Setup\GameOptions;
use Bga\Games\ProjectEdgeworth\Setup\GameSetup;
use Bga\Games\ProjectEdgeworth\Setup\SetupPlayer;
use ProjectEdgeworthTests\Support\ArrayGlobals;
use ProjectEdgeworthTests\Support\DatabaseTestCase;
use ProjectEdgeworthTests\Support\SeededRandom;

/**
 * 計画・アクションフェイズの結合テストの共通部品。GameSetup でゲームを開始し（Earth 配置: 表面 = 海・陸のみ、
 * 裏面 = 区域ボーナスあり）、ActionPhases を作る。プレイヤー ID は 101 から席順。
 */
abstract class ActionTestCase extends DatabaseTestCase
{
    protected GlobalValues $globals;
    protected ConfigurableHooks $hooks;
    protected ActionPhases $phases;
    protected BoardLayout $layout;
    /** @var list<int> */
    protected array $ids = [];

    protected function startGame(int $playerCount = 3, bool $advancedBoard = false): void
    {
        $colors = [SatelliteColor::Red, SatelliteColor::Green, SatelliteColor::Blue, SatelliteColor::Yellow];
        $this->ids = [];
        $players = [];
        for ($i = 0; $i < $playerCount; $i++) {
            $this->ids[] = 101 + $i;
            $players[] = new SetupPlayer(101 + $i, $colors[$i]);
        }
        $this->addPlayers($this->ids);
        $this->globals = new GlobalValues(new ArrayGlobals());
        $values = $advancedBoard ? [OptionId::BOARD_LAYOUT => 4] : [];
        $options = GameOptions::fromTableOptions($playerCount, static fn(int $id): ?int => $values[$id] ?? null);
        $rng = new SeededRandom(7);
        $this->layout = (new GameSetup($this->db, $this->globals, $rng))->run($players, $options);
        $this->hooks = new ConfigurableHooks();
        $this->phases = ActionPhases::load($this->db, $this->globals, $rng, $this->hooks);
        $this->globals->setRoundNo(1);
    }

    /** 指定の種類の区域 ID（昇順で $nth 番目） */
    protected function cellOf(AreaType $type, int $nth = 0): int
    {
        $found = 0;
        foreach ($this->layout->cellIds() as $cell) {
            if ($this->layout->areaType($cell) === $type) {
                if ($found === $nth) {
                    return $cell;
                }
                $found++;
            }
        }
        throw new \LogicException("no {$type->value} cell #$nth");
    }

    /** 手札をちょうどこのカードにする（それまでの手札は捨て札へ） */
    protected function setHand(int $playerId, array $cardNumbers): void
    {
        $this->db->execute(
            "UPDATE `project_card` SET `card_location` = 'discard', `card_location_arg` = 0 "
            . "WHERE `card_location` = 'hand' AND `card_location_arg` = $playerId",
        );
        foreach (array_values($cardNumbers) as $order => $number) {
            $this->db->execute(
                "UPDATE `project_card` SET `card_location` = 'hand', `card_location_arg` = $playerId, `card_order` = $order "
                . "WHERE `card_id` = $number",
            );
        }
    }

    protected function setMaya(int $playerId, int $maya): void
    {
        $this->phases->services()->players->setMaya($playerId, $maya);
    }

    protected function maya(int $playerId): int
    {
        return $this->phases->services()->players->maya($playerId);
    }

    /** @return list<Satellite> 形状順 */
    protected function sats(int $playerId): array
    {
        return $this->phases->services()->satellites->forPlayer($playerId);
    }

    protected function sat(int $playerId, int $index): Satellite
    {
        return $this->sats($playerId)[$index];
    }

    /** 衛星に配備済みにする（手札経由。ルール判定はしない） */
    protected function deployDirect(int $playerId, int $cardNumber, int $satId): void
    {
        $cards = $this->phases->services()->cards;
        $at = $cards->locationOf($cardNumber);
        if ($at['location'] !== CardLocation::Hand || $at['arg'] !== $playerId) {
            $this->setHand($playerId, array_merge($cards->idsIn(CardLocation::Hand, $playerId), [$cardNumber]));
        }
        $cards->deploy($playerId, $cardNumber, $satId);
    }

    /** 稼働中にする */
    protected function activate(int $satId, int $cell, Direction $dir = Direction::SE): void
    {
        $this->phases->services()->satellites->launch($satId, $cell, $dir);
    }

    /** 発射拠点を置く */
    protected function pad(int $playerId, int $cell): void
    {
        $this->phases->services()->players->setPadCell($playerId, $cell);
    }

    /** 他のカードの手札からカード番号を除いた「手札にあるカード」 */
    protected function handOf(int $playerId): array
    {
        return $this->phases->services()->cards->idsIn(CardLocation::Hand, $playerId);
    }
}
