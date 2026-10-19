<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Board;

/** 隣接・進行方向前後・距離（003 §6）。 */
final class Adjacency
{
    private function __construct()
    {
    }

    /**
     * 一般の隣接する区域（6 方向、左右は接続、上下は範囲外を除く）。
     *
     * @return list<Cell>
     */
    public static function neighbors(BoardGeometry $geometry, int $x, int $y): array
    {
        $result = [];
        foreach (Direction::cases() as $d) {
            $ny = $y + $d->dy();
            if (!$geometry->inRows($ny)) {
                continue;
            }
            $result[] = new Cell($geometry->wrapX($x + $d->dx()), $ny);
        }
        return $result;
    }

    /**
     * 進行方向前後の通信確立対象（直下・前方・後方。盤面外と反射後は対象外）。
     *
     * @return list<Cell>
     */
    public static function forwardBackward(BoardGeometry $geometry, int $x, int $y, Direction $dir): array
    {
        $result = [new Cell($geometry->wrapX($x), $y)];
        foreach ([1, -1] as $sign) {
            $ny = $y + $sign * $dir->dy();
            if ($geometry->inRows($ny)) {
                $result[] = new Cell($geometry->wrapX($x + $sign * $dir->dx()), $ny);
            }
        }
        return $result;
    }

    /** 距離（区域数）。左右の接続をまたぐ経路も考慮した最小値。 */
    public static function distance(BoardGeometry $geometry, int $x1, int $y1, int $x2, int $y2): int
    {
        $dy = abs($y1 - $y2);
        $best = PHP_INT_MAX;
        foreach ([0, 1, -1] as $k) {
            $dx = abs($x1 - ($x2 + $k * $geometry->period()));
            $best = min($best, $dy + max(0, intdiv($dx - $dy, 2)));
        }
        return $best;
    }

    public static function distanceBetweenCells(BoardGeometry $geometry, int $cellA, int $cellB): int
    {
        $a = $geometry->cellFromId($cellA);
        $b = $geometry->cellFromId($cellB);
        return self::distance($geometry, $a->x, $a->y, $b->x, $b->y);
    }
}
