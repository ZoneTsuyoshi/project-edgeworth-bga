<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Setup;

/** 衛星プランのオプション（ゲームオプション 103。001 §3-4）。 */
enum SatellitePlanOption: int
{
    case Moon = 1; // 全員 MOON 面
    case PlayerVariable = 2; // 各自の色のプラン面
}
