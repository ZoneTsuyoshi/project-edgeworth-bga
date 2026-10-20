<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Board;

use Bga\Games\projectedgeworth\Board\AreaType;
use Bga\Games\projectedgeworth\Board\BoardFace;
use Bga\Games\projectedgeworth\Board\RegionBoards;
use PHPUnit\Framework\TestCase;

final class RegionBoardsTest extends TestCase
{
    public function testAdvancedTotals(): void
    {
        $counts = [];
        foreach (RegionBoards::IDS as $id) {
            for ($r = 0; $r < 3; $r++) {
                for ($c = 0; $c < 3; $c++) {
                    $t = RegionBoards::areaType(BoardFace::ADVANCED, $id, $r, $c)->value;
                    $counts[$t] = ($counts[$t] ?? 0) + 1;
                }
            }
        }
        ksort($counts);
        $expected = ['sea' => 14, 'reef' => 23, 'deep_sea' => 6, 'land' => 7, 'forest' => 8, 'mountain' => 7, 'snow' => 7];
        ksort($expected);
        self::assertSame($expected, $counts);
        self::assertSame(72, array_sum($counts));
    }

    public function testStandardAndAdvancedCorrespondExceptG(): void
    {
        $mismatch = [];
        foreach (RegionBoards::IDS as $id) {
            for ($r = 0; $r < 3; $r++) {
                for ($c = 0; $c < 3; $c++) {
                    $s = RegionBoards::areaType(BoardFace::STANDARD, $id, $r, $c);
                    $a = RegionBoards::areaType(BoardFace::ADVANCED, $id, $r, $c);
                    self::assertContains($s, [AreaType::SEA, AreaType::LAND]);
                    if ($s->isSea() !== $a->isSea()) {
                        $mismatch[] = "{$id}({$r},{$c})";
                    }
                }
            }
        }
        // 003 §3.4・未確定 No.7: G の (2, 0) だけ表が海、裏が雪原
        self::assertSame(['G(2,0)'], $mismatch);
        self::assertSame(AreaType::SEA, RegionBoards::areaType(BoardFace::STANDARD, 'G', 2, 0));
        self::assertSame(AreaType::SNOW, RegionBoards::areaType(BoardFace::ADVANCED, 'G', 2, 0));
    }

    public function testSelectedCells(): void
    {
        // RA は p.4 の図（海 礁 雪／礁 森 森／陸 山 陸）
        self::assertSame(AreaType::SEA, RegionBoards::areaType(BoardFace::ADVANCED, 'A', 0, 0));
        self::assertSame(AreaType::SNOW, RegionBoards::areaType(BoardFace::ADVANCED, 'A', 0, 2));
        self::assertSame(AreaType::FOREST, RegionBoards::areaType(BoardFace::ADVANCED, 'A', 1, 1));
        self::assertSame(AreaType::MOUNTAIN, RegionBoards::areaType(BoardFace::ADVANCED, 'A', 2, 1));
        self::assertSame(AreaType::LAND, RegionBoards::areaType(BoardFace::STANDARD, 'A', 1, 1));
        self::assertSame(AreaType::SEA, RegionBoards::areaType(BoardFace::STANDARD, 'D', 1, 1));
    }

    public function testAreaTypeProperties(): void
    {
        foreach ([AreaType::SEA, AreaType::DEEP_SEA, AreaType::REEF] as $t) {
            self::assertTrue($t->isSea());
            self::assertSame([2, 1], [$t->areaCost(), $t->areaFame()]);
        }
        foreach ([AreaType::LAND, AreaType::FOREST, AreaType::MOUNTAIN, AreaType::SNOW] as $t) {
            self::assertTrue($t->isLand());
            self::assertSame([5, 2], [$t->areaCost(), $t->areaFame()]);
        }
    }

    public function testAlphaBeta(): void
    {
        self::assertTrue(RegionBoards::isAlpha('D'));
        self::assertFalse(RegionBoards::isAlpha('E'));
    }
}
