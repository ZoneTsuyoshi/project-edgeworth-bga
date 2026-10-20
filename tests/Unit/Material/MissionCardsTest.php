<?php

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Material;

use Bga\Games\projectedgeworth\Material\MissionCards;
use PHPUnit\Framework\TestCase;

final class MissionCardsTest extends TestCase
{
    public function testHas10CardsWithContiguousIds(): void
    {
        self::assertSame(
            ['M001', 'M002', 'M003', 'M004', 'M005', 'M006', 'M007', 'M008', 'M009', 'M010'],
            array_keys(MissionCards::all()),
        );
    }

    public function testCellCountsAndOriginFollowSpec002(): void
    {
        foreach (MissionCards::all() as $id => $card) {
            $cells = array_merge($card->easyCells, $card->extraCells);
            // 002 §8.2 の検算: M001〜M008 は合計 6 マス、M009・M010 は 7 マス
            self::assertCount((int) substr($id, 1) <= 8 ? 6 : 7, $cells, $id);
            self::assertCount(count($cells), array_unique(array_map(static fn (array $c): string => $c[0] . ',' . $c[1], $cells)), $id);

            // 原点は最上行の最左マス
            $minR = min(array_column($cells, 1));
            self::assertSame(0, $minR, $id);
            $topRow = array_filter($cells, static fn (array $c): bool => $c[1] === 0);
            self::assertSame(0, min(array_column($topRow, 0)), $id);
            self::assertLessThan($card->fameFull, $card->fameEasy, $id);
        }
    }

    public function testSpotChecksAgainstSpec002(): void
    {
        $m001 = MissionCards::get('M001');
        self::assertSame('LOOP', $m001->name);
        self::assertSame('loop', $m001->nameId);
        self::assertSame([5, 17], [$m001->fameEasy, $m001->fameFull]);
        self::assertSame([[0, 0], [-1, 1], [-1, 2]], $m001->easyCells);
        self::assertSame([[1, 0], [1, 1], [0, 2]], $m001->extraCells);

        $m007 = MissionCards::get('M007');
        self::assertSame('fish', $m007->nameId);
        self::assertSame([9, 17], [$m007->fameEasy, $m007->fameFull]);
        self::assertSame([[-2, 1], [-1, 1], [-3, 2], [-2, 2]], $m007->easyCells);

        $m010 = MissionCards::get('M010');
        self::assertSame('turtle', $m010->nameId);
        self::assertSame([5, 24], [$m010->fameEasy, $m010->fameFull]);
        self::assertSame([[0, 0], [1, 0], [-2, 2], [1, 2]], $m010->extraCells);
    }
}
