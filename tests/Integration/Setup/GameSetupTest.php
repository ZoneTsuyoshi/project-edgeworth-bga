<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Setup;

use Bga\Games\ProjectEdgeworth\Board\BoardFace;
use Bga\Games\ProjectEdgeworth\Data\ActionCardRepository;
use Bga\Games\ProjectEdgeworth\Data\ActionCardState;
use Bga\Games\ProjectEdgeworth\Data\CardLocation;
use Bga\Games\ProjectEdgeworth\Data\FlowPhase;
use Bga\Games\ProjectEdgeworth\Data\GlobalValues;
use Bga\Games\ProjectEdgeworth\Data\MissionRepository;
use Bga\Games\ProjectEdgeworth\Data\PlayerValues;
use Bga\Games\ProjectEdgeworth\Data\ProjectCardRepository;
use Bga\Games\ProjectEdgeworth\Data\RegionRepository;
use Bga\Games\ProjectEdgeworth\Data\SatelliteRepository;
use Bga\Games\ProjectEdgeworth\Material\RewardCards;
use Bga\Games\ProjectEdgeworth\Material\SatelliteColor;
use Bga\Games\ProjectEdgeworth\Material\SatellitePlan;
use Bga\Games\ProjectEdgeworth\Material\SatelliteShape;
use Bga\Games\ProjectEdgeworth\Setup\GameOptions;
use Bga\Games\ProjectEdgeworth\Setup\GameSetup;
use Bga\Games\ProjectEdgeworth\Setup\LaunchPadPlacement;
use Bga\Games\ProjectEdgeworth\Setup\SetupPlayer;
use PHPUnit\Framework\Attributes\DataProvider;
use ProjectEdgeworthTests\Support\ArrayGlobals;
use ProjectEdgeworthTests\Support\SeededRandom;

final class GameSetupTest extends SetupTestCase
{
    /** @return array<string, array{int}> */
    public static function playerCounts(): array
    {
        return ['2 players' => [2], '3 players' => [3], '4 players' => [4]];
    }

    #[DataProvider('playerCounts')]
    public function testCardsAreDealtAndPilesHaveTheRightSize(int $count): void
    {
        $this->runSetup($count);
        $cards = new ProjectCardRepository($this->db, new SeededRandom(0));
        $missions = new MissionRepository($this->db);
        $actions = new ActionCardRepository($this->db);
        $sats = new SatelliteRepository($this->db);

        $this->assertSame(99 - 5 * $count, $cards->countIn(CardLocation::Deck));
        $this->assertSame(0, $cards->countIn(CardLocation::Discard));
        $dealt = [];
        foreach ($this->players as $p) {
            $this->assertSame(5, $cards->countIn(CardLocation::Hand, $p->id));
            array_push($dealt, ...$cards->idsIn(CardLocation::Hand, $p->id));
            $this->assertCount(2, $missions->forPlayer($p->id));
            $this->assertCount(5, $sats->forPlayer($p->id));
            $this->assertCount(5, $actions->handOf($p->id));
        }
        $this->assertCount(5 * $count, array_unique($dealt), 'hands must not share cards');
        $this->assertCount(10 - 2 * $count, $missions->undealt());
        $this->assertSame(0, $cards->countIn(CardLocation::Drawn, 0));
        $this->assertSame(0, (int) $this->db->value("SELECT COUNT(*) FROM `project_card` WHERE `card_location` = 'satellite'"));
    }

    public function testMissionsAreDistinctAcrossPlayersAndInRange(): void
    {
        $this->runSetup(4);
        $missions = new MissionRepository($this->db);
        $all = [];
        foreach ($this->players as $p) {
            array_push($all, ...$missions->forPlayer($p->id));
        }
        $this->assertCount(8, array_unique($all));
        foreach ($all as $no) {
            $this->assertGreaterThanOrEqual(1, $no);
            $this->assertLessThanOrEqual(10, $no);
        }
    }

    #[DataProvider('playerCounts')]
    public function testSatellitesAreInactiveMoonCardsByDefault(int $count): void
    {
        $this->runSetup($count);
        $sats = new SatelliteRepository($this->db);
        foreach ($this->players as $p) {
            $list = $sats->forPlayer($p->id);
            $this->assertSame(SatelliteShape::cases(), array_map(static fn($s) => $s->shape, $list));
            foreach ($list as $i => $sat) {
                $this->assertSame(SatellitePlan::Moon, $sat->plan);
                $this->assertSame($p->color, $sat->color);
                $this->assertFalse($sat->isActive());
                $this->assertSame(0, $sat->fuelUsed);
                $this->assertSame('R00' . ($i + 1) . strtoupper($p->color->value[0]), $sat->cardId);
            }
        }
    }

    public function testPlayerVariablePlanUsesEachColorsPlanFace(): void
    {
        $this->runSetup(3, [103 => 2]);
        $sats = new SatelliteRepository($this->db);
        // 席順は赤・緑・青（SetupTestCase）
        $expected = [
            [SatellitePlan::Europa, ['R016', 'R017', 'R018', 'R019', 'R020']],
            [SatellitePlan::Deimos, ['R011', 'R012', 'R013', 'R014', 'R015']],
            [SatellitePlan::Phobos, ['R006', 'R007', 'R008', 'R009', 'R010']],
        ];
        foreach ($this->players as $i => $p) {
            $list = $sats->forPlayer($p->id);
            $this->assertSame($expected[$i][1], array_map(static fn($s) => $s->cardId, $list));
            foreach ($list as $sat) {
                $this->assertSame($expected[$i][0], $sat->plan);
            }
        }
    }

    public function testYellowPlayerVariablePlanIsGanymede(): void
    {
        $this->runSetup(4, [103 => 2]);
        $sats = new SatelliteRepository($this->db);
        $this->assertSame(
            ['R021', 'R022', 'R023', 'R024', 'R025'],
            array_map(static fn($s) => $s->cardId, $sats->forPlayer($this->players[3]->id)),
        );
    }

    public function testActionCardsStartInHandAndBudgetIs15(): void
    {
        $this->runSetup(2);
        $actions = new ActionCardRepository($this->db);
        $values = new PlayerValues($this->db);
        foreach ($this->players as $p) {
            foreach ([1, 2, 3, 4, 5] as $no) {
                $this->assertSame(ActionCardState::Hand, $actions->state($p->id, $no));
            }
            $this->assertSame(15, $values->budget($p->id));
            $this->assertNull($values->padCell($p->id));
        }
    }

    /** @return array<string, array{int, int, list<int>}> */
    public static function funds(): array
    {
        return [
            'standard 2' => [2, 1, [30, 31]],
            'standard 3' => [3, 1, [30, 31, 32]],
            'standard 4' => [4, 1, [30, 31, 32, 33]],
            'advanced 2' => [2, 4, [28, 30]],
            'advanced 3' => [3, 4, [28, 30, 32]],
            'advanced 4' => [4, 6, [28, 30, 32, 34]],
        ];
    }

    /** @param list<int> $expected */
    #[DataProvider('funds')]
    public function testStartingFundsBySeatAndBoardFace(int $count, int $layoutOption, array $expected): void
    {
        $this->runSetup($count, [100 => $layoutOption]);
        $values = new PlayerValues($this->db);
        $this->assertSame($expected, array_map(fn(SetupPlayer $p): int => $values->maya($p->id), $this->players));
    }

    public function testGlobalsAreInitialised(): void
    {
        $this->runSetup(3, [100 => 6]);
        $this->assertSame(0, $this->globals->roundNo());
        $this->assertSame(FlowPhase::Setup, $this->globals->flowPhase());
        $this->assertSame(0, $this->globals->flowStep());
        $this->assertSame([], $this->globals->bonusHolders());
        $this->assertSame($this->players[0]->id, $this->globals->basePlayer());
        $this->assertSame(BoardFace::ADVANCED, $this->globals->boardFace());
        $this->assertSame(12, $this->globals->boardCols());
        $this->assertContains($this->globals->rowShift(), [0, 1]);
        $this->assertCount(2, $this->globals->rewardCards());
    }

    /** @return array<string, array{int, int, int}> */
    public static function boardSizes(): array
    {
        return [
            '2x4' => [4, 8, 12], '2x3' => [3, 6, 9], '2x2' => [2, 4, 6],
        ];
    }

    #[DataProvider('boardSizes')]
    public function testBoardSizeIsSavedAsRegionRows(int $boardCount, int $rows, int $cols): void
    {
        $size = [4 => 1, 3 => 2, 2 => 3][$boardCount];
        $layout = $this->runSetup(2, [101 => $size]);
        $regions = (new RegionRepository($this->db))->all();
        $this->assertCount($rows, $regions);
        $this->assertSame($cols, $this->globals->boardCols());
        $this->assertSame($cols * 6, count($layout->cellIds()));
        $this->assertCount($rows, array_unique(array_column($regions, 'board')));
    }

    public function testEarthLayoutIsAtoDOverEtoH(): void
    {
        $this->runSetup(2, [100 => 1]);
        $regions = (new RegionRepository($this->db))->all();
        $this->assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], array_column($regions, 'board'));
        $this->assertSame(array_fill(0, 8, false), array_column($regions, 'flipped'));
        $this->assertSame(0, $this->globals->rowShift());
        $this->assertSame(BoardFace::STANDARD, $this->globals->boardFace());
    }

    /** @return array<string, array{int}> */
    public static function layoutOptions(): array
    {
        return ['1' => [1], '2' => [2], '3' => [3], '4' => [4], '5' => [5], '6' => [6]];
    }

    #[DataProvider('layoutOptions')]
    public function testEveryLayoutOptionStoresARestorableBoard(int $option): void
    {
        $layout = $this->runSetup(2, [100 => $option], $option * 17);
        $regions = (new RegionRepository($this->db))->all();
        $this->assertCount(8, array_unique(array_column($regions, 'board')));
        $this->assertSame($option >= 4 ? BoardFace::ADVANCED : BoardFace::STANDARD, $this->globals->boardFace());
        if (in_array($option, [1, 2, 4, 5], true)) {
            $this->assertSame(array_fill(0, 8, false), array_column($regions, 'flipped'));
        }
        $restored = LaunchPadPlacement::loadLayout(new RegionRepository($this->db), $this->globals);
        foreach ($layout->cellIds() as $cell) {
            $this->assertSame($layout->areaType($cell), $restored->areaType($cell));
            $this->assertSame($layout->region($cell), $restored->region($cell));
        }
    }

    public function testMulliganOptionStillDealsFiveCards(): void
    {
        $this->runSetup(2, [104 => 2]);
        $cards = new ProjectCardRepository($this->db, new SeededRandom(0));
        foreach ($this->players as $p) {
            $this->assertSame(5, $cards->countIn(CardLocation::Hand, $p->id));
        }
        $this->assertSame(0, $cards->countIn(CardLocation::Discard));
    }

    public function testRewardsAreSavedAccordingToTheOption(): void
    {
        $this->runSetup(2, [100 => 4, 102 => 2], 5);
        $ids = $this->globals->rewardCards();
        $this->assertCount(2, $ids);
        $classes = array_map(static fn(string $id): string => RewardCards::get($id)->class->value, $ids);
        sort($classes);
        $this->assertSame(['CUBE', 'OTHER'], $classes);
    }

    public function testStandardBoardNeverUsesAdvancedOnlyRewards(): void
    {
        for ($seed = 1; $seed <= 30; $seed++) {
            $this->runSetup(2, [102 => 3], $seed);
            foreach ($this->globals->rewardCards() as $id) {
                $this->assertNotContains($id, ['T017', 'T018', 'T019', 'T020', 'T021', 'T022', 'T023', 'T024']);
            }
        }
    }

    public function testSameSeedReproducesTheSameSetup(): void
    {
        $this->runSetup(4, [100 => 6, 102 => 3], 99);
        $first = $this->snapshot();
        $this->runSetup(4, [100 => 6, 102 => 3], 99);
        $this->assertSame($first, $this->snapshot());
    }

    public function testDifferentSeedChangesTheSetup(): void
    {
        $this->runSetup(4, [100 => 6, 102 => 3], 1);
        $first = $this->snapshot();
        $this->runSetup(4, [100 => 6, 102 => 3], 2);
        $this->assertNotSame($first['cards'], $this->snapshot()['cards']);
    }

    public function testInvalidPlayersAreRejected(): void
    {
        $setup = new GameSetup($this->db, new GlobalValues(new ArrayGlobals()), new SeededRandom(1));
        $red = new SetupPlayer(1, SatelliteColor::Red);
        $this->expectException(\InvalidArgumentException::class);
        $setup->run([$red, $red], new GameOptions());
    }

    public function testSinglePlayerIsRejected(): void
    {
        $setup = new GameSetup($this->db, new GlobalValues(new ArrayGlobals()), new SeededRandom(1));
        $this->expectException(\InvalidArgumentException::class);
        $setup->run([new SetupPlayer(1, SatelliteColor::Red)], new GameOptions());
    }
}
