<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Scoring;

use Bga\Games\projectedgeworth\Board\Adjacency;
use Bga\Games\projectedgeworth\Board\AreaType;
use Bga\Games\projectedgeworth\Board\BoardGeometry;
use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Material\Region;

/**
 * 名声計算が使う盤面の情報（区域の種類・地域・隣接）。BoardLayout から作る（テストでは直接作れる）。
 */
final class ScoringBoard
{
    /**
     * @param array<int, AreaType> $types 区域 ID => 種類
     * @param array<int, Region> $regions 区域 ID => 地域
     */
    public function __construct(
        private readonly BoardGeometry $geometry,
        private readonly array $types,
        private readonly array $regions,
    ) {
    }

    public static function fromLayout(BoardLayout $layout): self
    {
        $types = [];
        $regions = [];
        foreach ($layout->cellIds() as $id) {
            $types[$id] = $layout->areaType($id);
            $regions[$id] = $layout->region($id);
        }
        return new self($layout->geometry(), $types, $regions);
    }

    public function geometry(): BoardGeometry
    {
        return $this->geometry;
    }

    public function areaType(int $cellId): AreaType
    {
        return $this->types[$cellId];
    }

    public function region(int $cellId): Region
    {
        return $this->regions[$cellId];
    }

    /**
     * 隣接する区域 ID（左右は接続、上下は範囲外を除く。003 §6）。
     *
     * @return list<int>
     */
    public function neighbors(int $cellId): array
    {
        $c = $this->geometry->cellFromId($cellId);
        $ids = [];
        foreach (Adjacency::neighbors($this->geometry, $c->x, $c->y) as $n) {
            $ids[$this->geometry->cellId($n->x, $n->y)] = true;
        }
        return array_keys($ids);
    }

    /**
     * 区域の集合を、隣接でつながった「まとまり」に分ける（左右の端はつながる。FAQ 行 80・81）。
     *
     * @param list<int> $cells
     * @return list<list<int>> 各まとまりの区域 ID（昇順）
     */
    public function components(array $cells): array
    {
        $remaining = array_fill_keys($cells, true);
        $groups = [];
        foreach ($cells as $start) {
            if (!isset($remaining[$start])) {
                continue;
            }
            unset($remaining[$start]);
            $group = [$start];
            $queue = [$start];
            while ($queue !== []) {
                $cur = array_shift($queue);
                foreach ($this->neighbors($cur) as $n) {
                    if (isset($remaining[$n])) {
                        unset($remaining[$n]);
                        $group[] = $n;
                        $queue[] = $n;
                    }
                }
            }
            sort($group);
            $groups[] = $group;
        }
        return $groups;
    }
}
