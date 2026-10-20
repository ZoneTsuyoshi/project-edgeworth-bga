<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Setup;

/**
 * 初期手札のオプション（ゲームオプション 104。001 §3-5）。値 3（Draft）は初版では未実装のため持たない。
 */
enum StartingHand: int
{
    case None = 1;
    case Mulligan = 2;
}
