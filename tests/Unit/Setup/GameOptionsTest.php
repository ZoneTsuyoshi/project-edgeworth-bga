<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Setup;

use Bga\Games\projectedgeworth\Board\BoardFace;
use Bga\Games\projectedgeworth\Board\BoardPlacement;
use Bga\Games\projectedgeworth\Setup\GameOptions;
use Bga\Games\projectedgeworth\Setup\RewardSelection;
use Bga\Games\projectedgeworth\Setup\SatellitePlanOption;
use Bga\Games\projectedgeworth\Setup\PublicFame;
use Bga\Games\projectedgeworth\Setup\StartingHand;
use PHPUnit\Framework\TestCase;

final class GameOptionsTest extends TestCase
{
    /** @param array<int, int> $values */
    private static function options(int $players, array $values): GameOptions
    {
        return GameOptions::fromTableOptions($players, static fn(int $id): ?int => $values[$id] ?? null);
    }

    public function testDefaultsWhenNothingIsSet(): void
    {
        $o = self::options(4, []);
        $this->assertSame(BoardFace::STANDARD, $o->boardFace);
        $this->assertSame(BoardPlacement::EARTH, $o->boardPlacement);
        $this->assertSame(4, $o->boardCount);
        $this->assertSame(RewardSelection::InitialGame, $o->rewardSelection);
        $this->assertSame(SatellitePlanOption::Moon, $o->satellitePlan);
        $this->assertSame(StartingHand::None, $o->startingHand);
        $this->assertSame(PublicFame::Hidden, $o->publicFame);
    }

    public function testAllSixBoardLayouts(): void
    {
        $expected = [
            1 => [BoardFace::STANDARD, BoardPlacement::EARTH],
            2 => [BoardFace::STANDARD, BoardPlacement::RANDOM_NO_FLIP],
            3 => [BoardFace::STANDARD, BoardPlacement::RANDOM_FLIP],
            4 => [BoardFace::ADVANCED, BoardPlacement::EARTH],
            5 => [BoardFace::ADVANCED, BoardPlacement::RANDOM_NO_FLIP],
            6 => [BoardFace::ADVANCED, BoardPlacement::RANDOM_FLIP],
        ];
        foreach ($expected as $value => [$face, $placement]) {
            $o = self::options(2, [100 => $value]);
            $this->assertSame($face, $o->boardFace, "option 100 = {$value}");
            $this->assertSame($placement, $o->boardPlacement, "option 100 = {$value}");
        }
    }

    public function testOtherOptionValues(): void
    {
        $o = self::options(3, [102 => 2, 103 => 2, 104 => 2]);
        $this->assertSame(RewardSelection::CubeAndOther, $o->rewardSelection);
        $this->assertSame(SatellitePlanOption::PlayerVariable, $o->satellitePlan);
        $this->assertSame(StartingHand::Mulligan, $o->startingHand);
        $this->assertSame(RewardSelection::Random, self::options(3, [102 => 3])->rewardSelection);
    }

    public function testPublicFameOption(): void
    {
        $this->assertSame(PublicFame::Visible, self::options(2, [105 => 2])->publicFame);
        $this->assertSame(PublicFame::Hidden, self::options(2, [105 => 1])->publicFame);
        $this->expectException(\InvalidArgumentException::class);
        self::options(2, [105 => 3]);
    }

    public function testBoardSizeByPlayerCount(): void
    {
        $this->assertSame(4, self::options(2, [101 => 1])->boardCount);
        $this->assertSame(3, self::options(2, [101 => 2])->boardCount);
        $this->assertSame(2, self::options(2, [101 => 3])->boardCount);
        $this->assertSame(3, self::options(3, [101 => 2])->boardCount);
        $this->assertSame(4, self::options(4, [101 => 1])->boardCount);
    }

    /** @return array<string, array{int, int}> */
    public static function invalidBoardSizes(): array
    {
        return [
            '3 players 2x2' => [3, 3],
            '4 players 2x3' => [4, 2],
            '4 players 2x2' => [4, 3],
            'unknown size' => [2, 4],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidBoardSizes')]
    public function testInvalidBoardSizeIsRejected(int $players, int $size): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::options($players, [101 => $size]);
    }

    public function testDraftIsNotImplemented(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::options(2, [104 => 3]);
    }

    public function testUnknownValuesAndPlayerCountAreRejected(): void
    {
        foreach ([[100 => 7], [102 => 4], [103 => 3]] as $values) {
            try {
                self::options(2, $values);
                $this->fail('expected an exception for ' . json_encode($values));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        self::options(5, []);
    }
}
