<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Board;

use Bga\Games\projectedgeworth\Board\AreaType;
use Bga\Games\projectedgeworth\Board\BoardFace;
use Bga\Games\projectedgeworth\Board\BoardGeometry;
use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Board\BoardLayoutBuilder;
use Bga\Games\projectedgeworth\Board\BoardPlacement;
use Bga\Games\projectedgeworth\Board\RegionBoards;
use Bga\Games\projectedgeworth\Material\Region;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BoardLayoutBuilderTest extends TestCase
{
    public function testOptionMapping(): void
    {
        self::assertSame([BoardFace::STANDARD, BoardPlacement::EARTH], BoardPlacement::fromOptionValue(1));
        self::assertSame([BoardFace::STANDARD, BoardPlacement::RANDOM_NO_FLIP], BoardPlacement::fromOptionValue(2));
        self::assertSame([BoardFace::STANDARD, BoardPlacement::RANDOM_FLIP], BoardPlacement::fromOptionValue(3));
        self::assertSame([BoardFace::ADVANCED, BoardPlacement::EARTH], BoardPlacement::fromOptionValue(4));
        self::assertSame([BoardFace::ADVANCED, BoardPlacement::RANDOM_NO_FLIP], BoardPlacement::fromOptionValue(5));
        self::assertSame([BoardFace::ADVANCED, BoardPlacement::RANDOM_FLIP], BoardPlacement::fromOptionValue(6));
        $this->expectException(\InvalidArgumentException::class);
        BoardPlacement::fromOptionValue(7);
    }

    public function testEarth(): void
    {
        $layout = BoardLayoutBuilder::build(BoardFace::STANDARD, BoardPlacement::EARTH, 4, new SeededRandom(1));
        self::assertSame(0, $layout->geometry()->parity());
        self::assertSame(['A', 'B', 'C', 'D'], array_column($layout->placements()[0], 'boardId'));
        self::assertSame(['E', 'F', 'G', 'H'], array_column($layout->placements()[1], 'boardId'));
        self::assertSame([Region::A, Region::A, Region::A, Region::B], [$layout->region(0), $layout->region(1), $layout->region(2), $layout->region(3)]);
        self::assertSame(Region::E, $layout->region(3 * 12));
        // 縮小（未確定 No.1 の暫定）
        $small = BoardLayoutBuilder::build(BoardFace::ADVANCED, BoardPlacement::EARTH, 2, new SeededRandom(1));
        self::assertSame(['A', 'B'], array_column($small->placements()[0], 'boardId'));
        self::assertSame(['E', 'F'], array_column($small->placements()[1], 'boardId'));
        self::assertSame(36, count($small->cellIds()));
        $mid = BoardLayoutBuilder::build(BoardFace::ADVANCED, BoardPlacement::EARTH, 3, new SeededRandom(1));
        self::assertSame(['E', 'F', 'G'], array_column($mid->placements()[1], 'boardId'));
        self::assertSame(54, count($mid->cellIds()));
    }

    /** @return iterable<string, array{int, int}> */
    public static function optionsAndSizes(): iterable
    {
        foreach ([1, 2, 3, 4, 5, 6] as $opt) {
            foreach ([4, 3, 2] as $b) {
                yield "option {$opt} B={$b}" => [$opt, $b];
            }
        }
    }

    /** 全オプション・全サイズ・多数のシードで、盤面がきれいにつながる（003 §2.2・§2.3）。 */
    #[DataProvider('optionsAndSizes')]
    public function testConnectivityForAllOptions(int $option, int $b): void
    {
        [$face, $placement] = BoardPlacement::fromOptionValue($option);
        $sawParity = [];
        $sawFlip = false;
        for ($seed = 1; $seed <= 60; $seed++) {
            $layout = BoardLayoutBuilder::build($face, $placement, $b, new SeededRandom($seed));
            $g = $layout->geometry();
            $sawParity[$g->parity()] = true;
            self::assertSame(6 * 3 * $b, count($layout->cellIds()));
            self::assertSame(range(0, 18 * $b - 1), $layout->cellIds());

            $used = [];
            foreach ($layout->placements() as $br => $row) {
                self::assertCount($b, $row);
                $needAlpha = (($br + $g->parity()) % 2) === 0;
                foreach ($row as $bc => $p) {
                    self::assertNotContains($p['boardId'], $used, 'board used twice');
                    $used[] = $p['boardId'];
                    $sawFlip = $sawFlip || $p['flip'];
                    $alpha = RegionBoards::isAlpha($p['boardId']);
                    // flip はタイプの不一致で決まる。w/o Flip では不一致が起きない
                    self::assertSame($alpha !== $needAlpha, $p['flip']);
                    if ($placement !== BoardPlacement::RANDOM_FLIP) {
                        self::assertFalse($p['flip']);
                    }
                    // 行のずれが盤面全体の P と一致する（元のずれ方: α は lr = 1、β は lr = 0・2）
                    for ($lr = 0; $lr < 3; $lr++) {
                        $globalRow = 3 * $br + ($p['flip'] ? 2 - $lr : $lr);
                        $srcShifted = $alpha ? $lr === 1 : $lr !== 1;
                        // 180° 回転するとずれ方が反転する（α は β と同じずれ方になる。003 §2.3）
                        $shifted = $p['flip'] ? !$srcShifted : $srcShifted;
                        self::assertSame($shifted ? 1 : 0, $g->shift($globalRow));
                    }
                    // 区域の種類は回転前のものを写しただけ（003 §2.3）
                    for ($lr = 0; $lr < 3; $lr++) {
                        for ($lc = 0; $lc < 3; $lc++) {
                            $gr = 3 * $br + ($p['flip'] ? 2 - $lr : $lr);
                            $gc = 3 * $bc + ($p['flip'] ? 2 - $lc : $lc);
                            $id = $gr * $g->columns() + $gc;
                            self::assertSame(RegionBoards::areaType($face, $p['boardId'], $lr, $lc), $layout->areaType($id));
                            self::assertSame($p['boardId'], $layout->region($id)->value);
                        }
                    }
                }
            }
        }
        if ($placement === BoardPlacement::EARTH) {
            self::assertSame([0 => true], $sawParity);
        } else {
            self::assertSame([0 => true, 1 => true], $sawParity);
        }
        if ($placement === BoardPlacement::RANDOM_FLIP) {
            self::assertTrue($sawFlip, 'flip never occurred');
        }
    }

    public function testRandomNoFlipGroupsFollowParity(): void
    {
        for ($seed = 1; $seed <= 30; $seed++) {
            $layout = BoardLayoutBuilder::build(BoardFace::STANDARD, BoardPlacement::RANDOM_NO_FLIP, 4, new SeededRandom($seed));
            $top = array_column($layout->placements()[0], 'boardId');
            $bottom = array_column($layout->placements()[1], 'boardId');
            if ($layout->geometry()->parity() === 0) {
                self::assertEqualsCanonicalizing(['A', 'B', 'C', 'D'], $top);
                self::assertEqualsCanonicalizing(['E', 'F', 'G', 'H'], $bottom);
            } else {
                self::assertEqualsCanonicalizing(['E', 'F', 'G', 'H'], $top);
                self::assertEqualsCanonicalizing(['A', 'B', 'C', 'D'], $bottom);
            }
        }
    }

    public function testRegionsFullBoardHasEightKinds(): void
    {
        $layout = BoardLayoutBuilder::build(BoardFace::ADVANCED, BoardPlacement::RANDOM_FLIP, 4, new SeededRandom(7));
        $regions = array_unique(array_map(static fn(int $id): string => $layout->region($id)->value, $layout->cellIds()));
        sort($regions);
        self::assertSame(RegionBoards::IDS, array_values($regions));
        self::assertCount(count(Region::cases()), $regions);
    }

    /** 180° 回転の写像: (lr, lc) → (2 - lr, 2 - lc)、中央は動かない（003 §2.3） */
    public function testRotationMapping(): void
    {
        // P = 1 なら上段は β。α の RA を回転して上段左端に置く。
        $g = BoardGeometry::forBoardCount(2, 1);
        $layout = new BoardLayout(BoardFace::ADVANCED, $g, [
            [['boardId' => 'A', 'flip' => true], ['boardId' => 'E', 'flip' => false]],
            [['boardId' => 'F', 'flip' => false], ['boardId' => 'B', 'flip' => true]],
        ]);
        $w = $g->columns();
        // RA = 海 礁 雪／礁 森 森／陸 山 陸
        self::assertSame(AreaType::SNOW, $layout->areaType(2 * $w + 0));  // (0,2) → (2,0)
        self::assertSame(AreaType::SEA, $layout->areaType(2 * $w + 2));   // (0,0) → (2,2)
        self::assertSame(AreaType::FOREST, $layout->areaType(1 * $w + 1)); // 中央は動かない
        self::assertSame(AreaType::MOUNTAIN, $layout->areaType(0 * $w + 1)); // (2,1) → (0,1)
        self::assertSame(AreaType::LAND, $layout->areaType(0 * $w + 0));  // (2,2) → (0,0)
        // 行のずれ（P = 1 の上段）: 回転した RA は 0・2 行目がずれ、1 行目はずれない
        self::assertSame([1, 0, 1], [$g->shift(0), $g->shift(1), $g->shift(2)]);
        // 回転した RB（br = 1, bc = 1）: (0,0) 雪 → (5,5)、(0,1) 礁 → (5,4)
        self::assertSame(AreaType::SNOW, $layout->areaType(5 * $w + 5));
        self::assertSame(AreaType::REEF, $layout->areaType(5 * $w + 4));
    }
}
