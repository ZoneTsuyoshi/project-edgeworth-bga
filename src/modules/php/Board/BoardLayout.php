<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

use Bga\Games\projectedgeworth\Material\Region;

/**
 * 生成済みの盤面（ボード配置）。区域ごとの種類・地域・元のローカル座標を引ける（003 §2）。
 */
final class BoardLayout
{
    /** @var list<AreaType> 区域 ID => 種類 */
    private array $types = [];

    /** @var list<string> 区域 ID => 地域（A〜H） */
    private array $regions = [];

    /**
     * @param list<list<array{boardId: string, flip: bool}>> $placements [段 br][位置 bc]
     */
    public function __construct(
        private readonly BoardFace $face,
        private readonly BoardGeometry $geometry,
        private readonly array $placements,
    ) {
        $w = $geometry->columns();
        foreach ($placements as $br => $row) {
            foreach ($row as $bc => $p) {
                for ($lr = 0; $lr < 3; $lr++) {
                    for ($lc = 0; $lc < 3; $lc++) {
                        $type = RegionBoards::areaType($face, $p['boardId'], $lr, $lc);
                        $gr = 3 * $br + ($p['flip'] ? 2 - $lr : $lr);
                        $gc = 3 * $bc + ($p['flip'] ? 2 - $lc : $lc);
                        $id = $gr * $w + $gc;
                        $this->types[$id] = $type;
                        $this->regions[$id] = $p['boardId'];
                    }
                }
            }
        }
        ksort($this->types);
        ksort($this->regions);
    }

    public function face(): BoardFace
    {
        return $this->face;
    }

    public function geometry(): BoardGeometry
    {
        return $this->geometry;
    }

    /** @return list<list<array{boardId: string, flip: bool}>> */
    public function placements(): array
    {
        return $this->placements;
    }

    public function areaType(int $cellId): AreaType
    {
        return $this->types[$cellId];
    }

    /** 区域の地域 = 含まれる地域ボードの識別子（003 §7） */
    public function region(int $cellId): Region
    {
        return Region::from($this->regions[$cellId]);
    }

    /** @return list<int> 区域 ID の昇順 */
    public function cellIds(): array
    {
        return array_keys($this->types);
    }
}
