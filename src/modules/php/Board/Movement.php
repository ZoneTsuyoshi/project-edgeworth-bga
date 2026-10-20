<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/** 衛星コマの移動（003 §4.2）。 */
final class Movement
{
    private function __construct()
    {
    }

    /**
     * 1 区域進む。上下の端では先に反射（向きを上下反転）し、左右の端は接続する。
     * 反射は転回ではない。反射後の向きはそのまま保持する。
     */
    public static function move(BoardGeometry $geometry, SatellitePosition $pos): SatellitePosition
    {
        $dir = $pos->dir;
        if (($pos->y === 0 && $dir->dy() < 0) || ($pos->y === $geometry->rows() - 1 && $dir->dy() > 0)) {
            $dir = $dir->flipVertical();
        }
        return new SatellitePosition(
            $geometry->wrapX($pos->x + $dir->dx()),
            $pos->y + $dir->dy(),
            $dir,
        );
    }

    /** 反射が起きるか（転回ではない。「転回したとき」の効果は発動しない）。 */
    public static function willReflect(BoardGeometry $geometry, SatellitePosition $pos): bool
    {
        return ($pos->y === 0 && $pos->dir->dy() < 0)
            || ($pos->y === $geometry->rows() - 1 && $pos->dir->dy() > 0);
    }
}
