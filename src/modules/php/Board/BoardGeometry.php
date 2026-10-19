<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Board;

/**
 * 盤面の形（行数 6、列数 W = 3 × ボード数 B、行のずれ P）と座標変換（003 §1）。
 * 区域 ID は row × W + col。計算用の主座標はダブルド座標 (x, y)。
 */
final class BoardGeometry
{
    public const ROWS = 6;

    public function __construct(
        private readonly int $columns,
        private readonly int $parity,
    ) {
        if ($columns < 1 || $columns % 3 !== 0) {
            throw new \InvalidArgumentException("columns must be a positive multiple of 3: {$columns}");
        }
        if ($parity !== 0 && $parity !== 1) {
            throw new \InvalidArgumentException("parity must be 0 or 1: {$parity}");
        }
    }

    /** 1 段のボード枚数 B（4・3・2）から作る。 */
    public static function forBoardCount(int $boardCount, int $parity): self
    {
        return new self(3 * $boardCount, $parity);
    }

    public function columns(): int
    {
        return $this->columns;
    }

    public function rows(): int
    {
        return self::ROWS;
    }

    public function parity(): int
    {
        return $this->parity;
    }

    public function cellCount(): int
    {
        return $this->columns * self::ROWS;
    }

    /** x の周期 2W */
    public function period(): int
    {
        return 2 * $this->columns;
    }

    public function shift(int $row): int
    {
        return ($row + $this->parity) % 2;
    }

    public function cellId(int $x, int $y): int
    {
        if ($y < 0 || $y >= self::ROWS) {
            throw new \InvalidArgumentException("row out of range: {$y}");
        }
        $x = $this->wrapX($x);
        $col = intdiv($x - $this->shift($y), 2);
        if ($x - $this->shift($y) !== 2 * $col) {
            throw new \InvalidArgumentException("x has wrong parity for row {$y}: {$x}");
        }
        return $y * $this->columns + $col;
    }

    public function cellFromId(int $cellId): Cell
    {
        if ($cellId < 0 || $cellId >= $this->cellCount()) {
            throw new \InvalidArgumentException("cell id out of range: {$cellId}");
        }
        $row = intdiv($cellId, $this->columns);
        $col = $cellId % $this->columns;
        return new Cell(2 * $col + $this->shift($row), $row);
    }

    public function row(int $cellId): int
    {
        return intdiv($cellId, $this->columns);
    }

    public function col(int $cellId): int
    {
        return $cellId % $this->columns;
    }

    /** x を [0, 2W) に折り返す（左右の接続）。 */
    public function wrapX(int $x): int
    {
        $p = $this->period();
        return (($x % $p) + $p) % $p;
    }

    /** 行 y が盤面内か（上下は接続しない）。 */
    public function inRows(int $y): bool
    {
        return $y >= 0 && $y < self::ROWS;
    }

    /** @return array{0: int, 1: int, 2: int} キューブ座標 (q, r, s)（003 §1.3） */
    public function toCube(int $x, int $y): array
    {
        $q = intdiv($x - $y - $this->parity, 2);
        return [$q, $y, -$q - $y];
    }

    /** キューブ座標からダブルド座標 (x, y)。左右は折り返さない。 */
    public function fromCube(int $q, int $r): Cell
    {
        return new Cell(2 * $q + $r + $this->parity, $r);
    }
}
