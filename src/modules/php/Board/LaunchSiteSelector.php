<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Board;

/** 発射拠点配置の zombie 既定（009 §9.2）。 */
final class LaunchSiteSelector
{
    private function __construct()
    {
    }

    /**
     * 空いている陸区域のうち、他の発射拠点までの最短距離が最大の区域（同点すべて）。
     * 他の発射拠点がなければ空いている陸区域すべて。
     *
     * @param list<int> $occupiedCells 既に置かれた発射拠点の区域 ID
     * @return list<int>
     */
    public static function candidates(BoardLayout $layout, array $occupiedCells): array
    {
        $geometry = $layout->geometry();
        $best = -1;
        $result = [];
        foreach ($layout->cellIds() as $cell) {
            if (!$layout->areaType($cell)->isLand() || in_array($cell, $occupiedCells, true)) {
                continue;
            }
            $min = PHP_INT_MAX;
            foreach ($occupiedCells as $other) {
                $min = min($min, Adjacency::distanceBetweenCells($geometry, $cell, $other));
            }
            if ($occupiedCells === []) {
                $min = 0;
            }
            if ($min > $best) {
                $best = $min;
                $result = [$cell];
            } elseif ($min === $best) {
                $result[] = $cell;
            }
        }
        return $result;
    }

    /**
     * @param list<int> $occupiedCells
     * @return int|null 空き陸区域がなければ null
     */
    public static function choose(BoardLayout $layout, array $occupiedCells, RandomSource $rng): ?int
    {
        $candidates = self::candidates($layout, $occupiedCells);
        if ($candidates === []) {
            return null;
        }
        return $candidates[$rng->rand(0, count($candidates) - 1)];
    }
}
