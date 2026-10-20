<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Board;

use Bga\Games\projectedgeworth\Board\BoardGeometry;
use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Board\Movement;
use Bga\Games\projectedgeworth\Board\SatellitePosition;
use PHPUnit\Framework\TestCase;

/** RB p.18 例 1〜3（003 §4.2）。W = 12（2×4）、P = 0 の盤面で検証する。 */
final class MovementTest extends TestCase
{
    private function geometry(int $p = 0, int $b = 4): BoardGeometry
    {
        return BoardGeometry::forBoardCount($b, $p);
    }

    /** @return array{int, int, string} */
    private function step(BoardGeometry $g, int $x, int $y, Direction $d): array
    {
        $n = Movement::move($g, new SatellitePosition($x, $y, $d));
        return [$n->x, $n->y, $n->dir->value];
    }

    public function testExample1RightEdgeConnects(): void
    {
        $g = $this->geometry();
        // 右端（行 1、x = 23）で右上を向く → 左端の上の段へ、向きは同じ
        self::assertSame([0, 0, 'NE'], $this->step($g, 23, 1, Direction::NE));
        // 右端（行 2、x = 22）で右上 → 同じ行の右端のまま 1 つ上の段
        self::assertSame([23, 1, 'NE'], $this->step($g, 22, 2, Direction::NE));
        // 左端で左下 → 右端へ
        self::assertSame([23, 3, 'SW'], $this->step($g, 0, 2, Direction::SW));
    }

    public function testExample2TopReflection(): void
    {
        $g = $this->geometry();
        self::assertSame([11, 1, 'SE'], $this->step($g, 10, 0, Direction::NE));
        self::assertSame([9, 1, 'SW'], $this->step($g, 10, 0, Direction::NW));
        // 反射した向きはそのまま続く
        self::assertSame([12, 2, 'SE'], $this->step($g, 11, 1, Direction::SE));
    }

    public function testBottomReflection(): void
    {
        $g = $this->geometry();
        self::assertSame([5, 4, 'NE'], $this->step($g, 4, 5, Direction::SE));
        self::assertSame([11, 4, 'NW'], $this->step($g, 12, 5, Direction::SW));
    }

    public function testExample3CornerReflectThenConnect(): void
    {
        $g = $this->geometry();
        // 右下端（行 5、x = 23）で右下 → 先に反射して右上、左端の 1 つ上の段へ接続し右上のまま
        self::assertSame([0, 4, 'NE'], $this->step($g, 23, 5, Direction::SE));
        // 右上端（行 0、x = 22）で右上 → 反射して右下、左端の下の段（x = 23 ではなく 0）
        self::assertSame([23, 1, 'SE'], $this->step($g, 22, 0, Direction::NE));
        // 左上端（行 0、x = 0）で左上 → 反射して左下、右端の下の段
        self::assertSame([23, 1, 'SW'], $this->step($g, 0, 0, Direction::NW));
        // 左下端（行 5、x = 1）で左下 → 反射して左上、右端（P = 0 の行 4 は偶数 x）
        self::assertSame([0, 4, 'NW'], $this->step($g, 1, 5, Direction::SW));
    }

    public function testWillReflect(): void
    {
        $g = $this->geometry();
        self::assertTrue(Movement::willReflect($g, new SatellitePosition(2, 0, Direction::NE)));
        self::assertFalse(Movement::willReflect($g, new SatellitePosition(2, 0, Direction::SE)));
        self::assertTrue(Movement::willReflect($g, new SatellitePosition(1, 5, Direction::SW)));
        self::assertFalse(Movement::willReflect($g, new SatellitePosition(1, 3, Direction::SW)));
    }

    /** 真横（E / W）は同じ段を 1 区域（x ± 2）ずつ進み、左右の端は反対側へつながる。向きは変わらない（作者回答 2026-10-20） */
    public function testEastWestMoveAlongTheRowAndConnectAtTheEdges(): void
    {
        $g = $this->geometry();
        self::assertSame([12, 2, 'E'], $this->step($g, 10, 2, Direction::E));
        self::assertSame([8, 2, 'W'], $this->step($g, 10, 2, Direction::W));
        // 右端の次は左端（W = 12、周期 24）。段によってずれが違うので x の偶奇に注意
        self::assertSame([0, 2, 'E'], $this->step($g, 22, 2, Direction::E));
        self::assertSame([22, 2, 'W'], $this->step($g, 0, 2, Direction::W));
        self::assertSame([1, 1, 'E'], $this->step($g, 23, 1, Direction::E));
        self::assertSame([23, 1, 'W'], $this->step($g, 1, 1, Direction::W));
    }

    public function testEastWestNeverReflectAtTopAndBottomRows(): void
    {
        $g = $this->geometry();
        foreach ([Direction::E, Direction::W] as $d) {
            foreach ([0, 5] as $y) {
                $x = $y % 2 === 0 ? 10 : 11;
                self::assertFalse(Movement::willReflect($g, new SatellitePosition($x, $y, $d)));
                self::assertSame($y, Movement::move($g, new SatellitePosition($x, $y, $d))->y);
                self::assertSame($d, Movement::move($g, new SatellitePosition($x, $y, $d))->dir);
            }
        }
    }

    /** どの区域・どの斜め向き・どの盤面でも移動先は盤面内の有効な区域になる。 */
    public function testAlwaysLandsOnValidCell(): void
    {
        foreach ([0, 1] as $p) {
            foreach ([4, 3, 2] as $b) {
                $g = $this->geometry($p, $b);
                for ($id = 0; $id < $g->cellCount(); $id++) {
                    $c = $g->cellFromId($id);
                    foreach (Direction::cases() as $d) {
                        $n = Movement::move($g, new SatellitePosition($c->x, $c->y, $d));
                        self::assertGreaterThanOrEqual(0, $n->y);
                        self::assertLessThan(6, $n->y);
                        self::assertIsInt($g->cellId($n->x, $n->y));
                    }
                }
            }
        }
    }

    public function testFullOrbitReturns(): void
    {
        // 衛星は上下に反射しながら進み、左右の周期 2W の整数倍の手数で元の列に戻る
        $g = $this->geometry(0, 2);
        $pos = new SatellitePosition(0, 0, Direction::SE);
        for ($i = 0; $i < 4 * 12; $i++) {
            $pos = Movement::move($g, $pos);
        }
        self::assertSame(0, $pos->x % 12);
    }
}
