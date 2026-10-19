<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Setup;

use Bga\Games\ProjectEdgeworth\Board\BoardLayout;
use Bga\Games\ProjectEdgeworth\Board\BoardLayoutBuilder;
use Bga\Games\ProjectEdgeworth\Board\RandomSource;
use Bga\Games\ProjectEdgeworth\Data\ActionCardRepository;
use Bga\Games\ProjectEdgeworth\Data\Database;
use Bga\Games\ProjectEdgeworth\Data\GlobalValues;
use Bga\Games\ProjectEdgeworth\Data\MissionRepository;
use Bga\Games\ProjectEdgeworth\Data\PlayerValues;
use Bga\Games\ProjectEdgeworth\Data\ProjectCardRepository;
use Bga\Games\ProjectEdgeworth\Data\RegionRepository;
use Bga\Games\ProjectEdgeworth\Data\SatelliteRepository;
use Bga\Games\ProjectEdgeworth\Material\SatelliteCards;
use Bga\Games\ProjectEdgeworth\Material\SatelliteColor;
use Bga\Games\ProjectEdgeworth\Material\SatellitePlan;
use Bga\Games\ProjectEdgeworth\Material\SatelliteShape;

/**
 * ゲーム開始時の DB の初期化（001 §5 手順 1〜9・11〜15、009 §3.3、010 §15）。BGA の Table に依存しない。
 * Game::setupNewGame が席順のプレイヤーとオプションを渡して run() を呼び、初期状態として SetupLaunchPadNext（10）を返す。
 *
 * 含まないもの: 発射拠点の配置（手順 10。状態 10・11。LaunchPadPlacement が問い合わせだけ用意する）、
 * マリガン、配備フェイズ（手順 16）、通知、統計の init。
 */
final class GameSetup
{
    /** 初期の手札（プロジェクトカード）の枚数（手順 14） */
    public const HAND_SIZE = 5;
    /** 各プレイヤーに配るミッションの枚数（手順 13） */
    public const MISSIONS_PER_PLAYER = 2;
    /** 使うミッションカードの総数（M001〜M010） */
    private const MISSION_COUNT = 10;

    private readonly ProjectCardRepository $projectCards;
    private readonly RegionRepository $regions;
    private readonly SatelliteRepository $satellites;
    private readonly ActionCardRepository $actionCards;
    private readonly MissionRepository $missions;
    private readonly PlayerValues $players;

    public function __construct(
        Database $db,
        private readonly GlobalValues $globals,
        private readonly RandomSource $rng,
    ) {
        $this->projectCards = new ProjectCardRepository($db, $rng);
        $this->regions = new RegionRepository($db);
        $this->satellites = new SatelliteRepository($db);
        $this->actionCards = new ActionCardRepository($db);
        $this->missions = new MissionRepository($db);
        $this->players = new PlayerValues($db);
    }

    /**
     * 全テーブルが空で、player 行が作られていることを前提にする。
     *
     * @param list<SetupPlayer> $players 席順（先頭が基準プレイヤー。Q-07）
     * @return BoardLayout 生成した盤面
     */
    public function run(array $players, GameOptions $options): BoardLayout
    {
        $count = count($players);
        if ($count < 2 || $count > 4) {
            throw new \InvalidArgumentException("unsupported number of players: {$count}");
        }
        if (count(array_unique(array_map(static fn(SetupPlayer $p): string => $p->color->value, $players))) !== $count) {
            throw new \InvalidArgumentException('player colors must be distinct');
        }

        $this->globals->initialize();
        $this->globals->setBasePlayer($players[0]->id);

        // 手順 3: プロジェクトカードの山札
        $this->projectCards->createAll();
        $this->projectCards->shuffleDeck();

        // 手順 1: 地域ボードの配置
        $layout = $this->setUpBoard($options);

        // 手順 5: 褒賞カード 2 枚
        $this->globals->setRewardCards(RewardSelector::select($options->rewardSelection, $options->boardFace, $this->rng));

        // 手順 8・9・11・12・15: 衛星カード、アクションカード、予算、初期資金
        foreach ($players as $seat => $player) {
            $this->createSatellites($player, $options->satellitePlan);
            $this->actionCards->createFor($player->id);
            $this->players->setBudget($player->id, PlayerValues::INITIAL_BUDGET);
            $this->players->setMaya($player->id, StartingFunds::forSeat($options->boardFace, $seat));
        }

        // 手順 13: ミッション 2 枚
        $this->dealMissions($players);

        // 手順 14: プロジェクトカード 5 枚（席順に直列に引く。010 §4.3）
        foreach ($players as $player) {
            $this->projectCards->drawTop($player->id, self::HAND_SIZE);
        }

        return $layout;
    }

    private function setUpBoard(GameOptions $options): BoardLayout
    {
        $layout = BoardLayoutBuilder::build($options->boardFace, $options->boardPlacement, $options->boardCount, $this->rng);
        foreach ($layout->placements() as $boardRow => $row) {
            foreach ($row as $boardCol => $placement) {
                $this->regions->place($boardRow, $boardCol, $placement['boardId'], $placement['flip']);
            }
        }
        $this->globals->setBoardCols($layout->geometry()->columns());
        $this->globals->setBoardFace($layout->face());
        $this->globals->setRowShift($layout->geometry()->parity());
        return $layout;
    }

    /**
     * 5 機を非稼働で作る。面は 1 人につき全機そろえる（001 §3-4）。MOON は色別の面（R001B など）、
     * Player-variable は自分の色のプランの面（青 PHOBOS・緑 DEIMOS・赤 EUROPA・黄 GANYMEDE。002 §6.2）。
     */
    private function createSatellites(SetupPlayer $player, SatellitePlanOption $option): void
    {
        $plan = $option === SatellitePlanOption::Moon ? SatellitePlan::Moon : match ($player->color) {
            SatelliteColor::Blue => SatellitePlan::Phobos,
            SatelliteColor::Green => SatellitePlan::Deimos,
            SatelliteColor::Red => SatellitePlan::Europa,
            SatelliteColor::Yellow => SatellitePlan::Ganymede,
        };
        foreach (SatelliteShape::cases() as $shape) {
            $card = null;
            foreach (SatelliteCards::all() as $definition) {
                if ($definition->plan === $plan && $definition->color === $player->color && $definition->shape === $shape) {
                    $card = $definition;
                    break;
                }
            }
            if ($card === null) {
                throw new \LogicException("satellite card not found: {$plan->value} {$player->color->value} {$shape->value}");
            }
            $this->satellites->create($player->id, $player->color, $shape, $plan, $card->id);
        }
    }

    /**
     * 10 枚をシャッフルし、席順に 2 枚ずつ配る。残りは使わない。
     *
     * @param list<SetupPlayer> $players
     */
    private function dealMissions(array $players): void
    {
        $this->missions->createAll();
        $nos = range(1, self::MISSION_COUNT);
        for ($i = count($nos) - 1; $i > 0; $i--) {
            $j = $this->rng->rand(0, $i);
            [$nos[$i], $nos[$j]] = [$nos[$j], $nos[$i]];
        }
        foreach ($players as $seat => $player) {
            $this->missions->deal($player->id, array_slice($nos, $seat * self::MISSIONS_PER_PLAYER, self::MISSIONS_PER_PLAYER));
        }
    }
}
