<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Board;

use Bga\Games\projectedgeworth\Board\BoardGeometry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BoardGeometryTest extends TestCase
{
    /** @return iterable<string, array{int, int}> */
    public static function geometries(): iterable
    {
        foreach ([4, 3, 2] as $b) {
            foreach ([0, 1] as $p) {
                yield "B={$b} P={$p}" => [$b, $p];
            }
        }
    }

    #[DataProvider('geometries')]
    public function testCellRoundTripAndParity(int $b, int $p): void
    {
        $g = BoardGeometry::forBoardCount($b, $p);
        self::assertSame(6, $g->rows());
        self::assertSame(3 * $b, $g->columns());
        self::assertSame(18 * $b, $g->cellCount());
        self::assertSame(6 * $b, $g->period());
        for ($id = 0; $id < $g->cellCount(); $id++) {
            $c = $g->cellFromId($id);
            self::assertSame($id, $g->cellId($c->x, $c->y));
            // x - y - P は常に偶数（003 §1.3）
            self::assertSame(0, ($c->x - $c->y - $p) % 2);
            [$q, $r, $s] = $g->toCube($c->x, $c->y);
            self::assertSame(0, $q + $r + $s);
            $back = $g->fromCube($q, $r);
            self::assertSame([$c->x, $c->y], [$back->x, $back->y]);
        }
    }

    public function testShiftAndExamples(): void
    {
        $g0 = BoardGeometry::forBoardCount(4, 0);
        self::assertSame(0, $g0->shift(0));
        self::assertSame(1, $g0->shift(1));
        $g1 = BoardGeometry::forBoardCount(4, 1);
        self::assertSame(1, $g1->shift(0));
        // 003 §1.2: x = 2 col + shift
        self::assertSame(23, $g0->cellFromId(1 * 12 + 11)->x);
        self::assertSame(1, $g1->cellFromId(0)->x);
        // 003 §1.4: cell = row × W + col
        self::assertSame(2 * 12 + 5, $g0->cellId(10, 2));
    }

    public function testWrapX(): void
    {
        $g = BoardGeometry::forBoardCount(2, 0);
        self::assertSame(11, $g->wrapX(-1));
        self::assertSame(0, $g->wrapX(12));
        self::assertSame(5, $g->wrapX(29));
    }

    public function testRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BoardGeometry::forBoardCount(2, 0)->cellId(1, 0); // 行 0 は偶数 x だけ
    }
}
