<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Board;

use Bga\Games\ProjectEdgeworth\Board\MissionShapes;
use PHPUnit\Framework\TestCase;

final class MissionShapesTest extends TestCase
{
    /**
     * 003 §8.1 の表（ダブルド座標 (row, x)、名声）。MissionCards（002 のアキシャル座標）からの変換結果と照合する。
     */
    private const SPEC_003 = [
        1 => [[[0, 7], [1, 6], [2, 7]], [[0, 9], [1, 10], [2, 9]], 5, 17],
        2 => [[[0, 6], [1, 5], [1, 7]], [[0, 10], [1, 9], [1, 11]], 5, 18],
        3 => [[[0, 7], [1, 8], [2, 7]], [[0, 9], [1, 10], [2, 9]], 5, 18],
        4 => [[[2, 6], [2, 8], [2, 10]], [[0, 8], [1, 7], [1, 9]], 5, 18],
        5 => [[[0, 7], [0, 9], [1, 6], [1, 10]], [[2, 5], [2, 11]], 8, 15],
        6 => [[[1, 5], [1, 7], [1, 9], [1, 11]], [[0, 8], [2, 8]], 7, 16],
        7 => [[[1, 6], [1, 8], [2, 5], [2, 7]], [[0, 9], [1, 10]], 9, 17],
        8 => [[[0, 6], [0, 8], [1, 5], [1, 7]], [[0, 10], [1, 9]], 9, 17],
        9 => [[[1, 6], [1, 8], [1, 10]], [[0, 7], [0, 9], [2, 7], [2, 9]], 5, 24],
        10 => [[[1, 6], [1, 8], [1, 10]], [[0, 7], [0, 9], [2, 5], [2, 11]], 5, 24],
    ];

    public function testTenMissions(): void
    {
        self::assertSame(range(1, 10), array_keys(MissionShapes::all()));
        self::assertSame(
            ['LOOP', 'BUMP', 'ARCH', 'PILE', 'RECOIL', 'WING', 'FISH', 'EDGE', 'LOCK', 'TURTLE'],
            array_map(static fn($s) => $s->name, array_values(MissionShapes::all())),
        );
    }

    public function testConvertedShapesMatchSpec003(): void
    {
        foreach (self::SPEC_003 as $id => [$solid, $hatched, $easy, $extra]) {
            $shape = MissionShapes::get($id);
            self::assertSame([$easy, $extra], [$shape->fameEasy, $shape->fameExtra], "fame {$id}");
            // 左上基準（最上行の最も左）に平行移動して比べる（x の原点は変換元で異なる）
            $normalize = static function (array $solidCells, array $hatchedCells): array {
                $all = array_merge($solidCells, $hatchedCells);
                $topRow = min(array_column($all, 0));
                $x0 = min(array_map(static fn(array $c): int => $c[1], array_filter($all, static fn(array $c): bool => $c[0] === $topRow)));
                $shift = static function (array $cells) use ($topRow, $x0): array {
                    $r = array_map(static fn(array $c): array => [$c[0] - $topRow, $c[1] - $x0], $cells);
                    sort($r);
                    return $r;
                };
                return [$shift($solidCells), $shift($hatchedCells)];
            };
            self::assertSame($normalize($solid, $hatched), $normalize($shape->solid, $shape->hatched), "shape {$id}");
        }
    }
}
