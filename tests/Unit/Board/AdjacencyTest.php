<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Board;

use Bga\Games\projectedgeworth\Board\Adjacency;
use Bga\Games\projectedgeworth\Board\BoardGeometry;
use Bga\Games\projectedgeworth\Board\Cell;
use Bga\Games\projectedgeworth\Board\Direction;
use PHPUnit\Framework\TestCase;

final class AdjacencyTest extends TestCase
{
    /**
     * @param list<Cell> $cells
     * @return list<string>
     */
    private function xy(array $cells): array
    {
        $r = array_map(static fn(Cell $c): string => "{$c->x},{$c->y}", $cells);
        sort($r);
        return $r;
    }

    public function testNeighborCounts(): void
    {
        foreach ([0, 1] as $p) {
            $g = BoardGeometry::forBoardCount(4, $p);
            for ($id = 0; $id < $g->cellCount(); $id++) {
                $c = $g->cellFromId($id);
                $n = Adjacency::neighbors($g, $c->x, $c->y);
                // 上端・下端は最大 4、それ以外は 6（003 §6.2）
                self::assertCount($c->y === 0 || $c->y === 5 ? 4 : 6, $n);
                foreach ($n as $nc) {
                    self::assertIsInt($g->cellId($nc->x, $nc->y));
                    self::assertSame(1, Adjacency::distance($g, $c->x, $c->y, $nc->x, $nc->y));
                }
            }
        }
    }

    public function testNeighborsWrapAcrossEdges(): void
    {
        $g = BoardGeometry::forBoardCount(4, 0); // 周期 24
        self::assertSame($this->xy([
            new Cell(1, 1), new Cell(22, 0), new Cell(2, 0), new Cell(23, 1),
        ]), $this->xy(Adjacency::neighbors($g, 0, 0)));
        // 行 1 の右端（x = 23）: 右は x = 25 → 1 ではなく 1 (mod 24)
        self::assertContains('1,1', $this->xy(Adjacency::neighbors($g, 23, 1)));
        self::assertContains('0,0', $this->xy(Adjacency::neighbors($g, 23, 1)));
        self::assertContains('0,2', $this->xy(Adjacency::neighbors($g, 23, 1)));
    }

    /** RB p.17 例 4〜6（003 §6.1） */
    public function testForwardBackward(): void
    {
        $g = BoardGeometry::forBoardCount(4, 0);
        // 例 4: 中段の右上向き: 直下・前方（右上）・後方（左下）
        self::assertSame($this->xy([new Cell(10, 2), new Cell(11, 1), new Cell(9, 3)]), $this->xy(Adjacency::forwardBackward($g, 10, 2, Direction::NE)));
        // 例 5: 上端の行で右上向き: 前方は盤面外で対象外、後方は対象
        self::assertSame($this->xy([new Cell(10, 0), new Cell(9, 1)]), $this->xy(Adjacency::forwardBackward($g, 10, 0, Direction::NE)));
        // 下端の行で右下向きでも同様
        self::assertSame($this->xy([new Cell(11, 5), new Cell(10, 4)]), $this->xy(Adjacency::forwardBackward($g, 11, 5, Direction::SE)));
        // 例 6: 左端で左下向き: 前方は右端の下の段（x = -1 mod 24 = 23）、後方は右上
        self::assertSame($this->xy([new Cell(0, 2), new Cell(23, 3), new Cell(1, 1)]), $this->xy(Adjacency::forwardBackward($g, 0, 2, Direction::SW)));
    }

    public function testForwardBackwardEdgeCases(): void
    {
        $g = BoardGeometry::forBoardCount(4, 0);
        // 行 0 で左下向き: 後方（右上）は盤面外、前方は対象
        self::assertSame($this->xy([new Cell(4, 0), new Cell(3, 1)]), $this->xy(Adjacency::forwardBackward($g, 4, 0, Direction::SW)));
    }

    public function testDistance(): void
    {
        $g = BoardGeometry::forBoardCount(4, 0);
        self::assertSame(0, Adjacency::distance($g, 4, 2, 4, 2));
        self::assertSame(1, Adjacency::distance($g, 0, 0, 22, 0)); // 左右の接続
        self::assertSame(1, Adjacency::distance($g, 0, 0, 23, 1));
        self::assertSame(2, Adjacency::distance($g, 0, 0, 0, 2));
        self::assertSame(5, Adjacency::distance($g, 0, 0, 10, 0));
        self::assertSame(5, Adjacency::distance($g, 0, 0, 14, 0)); // 左右の接続を回る方が近い（24 - 14 = 10）
        self::assertSame(6, Adjacency::distance($g, 0, 0, 12, 0)); // 反対側（周期 24 の半分）
    }

    /** 距離は隣接の BFS（左右の接続あり）と一致する。 */
    public function testDistanceMatchesBfs(): void
    {
        foreach ([0, 1] as $p) {
            foreach ([2, 3] as $b) {
                $g = BoardGeometry::forBoardCount($b, $p);
                for ($start = 0; $start < $g->cellCount(); $start++) {
                    $dist = [$start => 0];
                    $queue = [$start];
                    while ($queue !== []) {
                        $cur = array_shift($queue);
                        $c = $g->cellFromId($cur);
                        foreach (Adjacency::neighbors($g, $c->x, $c->y) as $n) {
                            $id = $g->cellId($n->x, $n->y);
                            if (!isset($dist[$id])) {
                                $dist[$id] = $dist[$cur] + 1;
                                $queue[] = $id;
                            }
                        }
                    }
                    for ($other = 0; $other < $g->cellCount(); $other++) {
                        self::assertSame($dist[$other], Adjacency::distanceBetweenCells($g, $start, $other), "P={$p} B={$b} {$start}->{$other}");
                    }
                }
            }
        }
    }
}
