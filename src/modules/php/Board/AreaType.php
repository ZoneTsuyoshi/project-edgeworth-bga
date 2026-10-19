<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Board;

/** 区域の種類（003 §3.1）。区域コスト・区域名声は 006 が使う。 */
enum AreaType: string
{
    case SEA = 'sea';
    case LAND = 'land';
    case DEEP_SEA = 'deep_sea';
    case REEF = 'reef';
    case FOREST = 'forest';
    case MOUNTAIN = 'mountain';
    case SNOW = 'snow';

    /** 総称「海」（標準海・深海・岩礁） */
    public function isSea(): bool
    {
        return match ($this) {
            self::SEA, self::DEEP_SEA, self::REEF => true,
            default => false,
        };
    }

    /** 総称「陸」（標準陸・森林・山岳・雪原） */
    public function isLand(): bool
    {
        return !$this->isSea();
    }

    public function areaCost(): int
    {
        return $this->isSea() ? 2 : 5;
    }

    public function areaFame(): int
    {
        return $this->isSea() ? 1 : 2;
    }
}
