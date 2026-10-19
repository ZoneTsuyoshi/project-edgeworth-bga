<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Setup;

/** 褒賞カードの選び方（ゲームオプション 102。001 §3-3）。 */
enum RewardSelection: int
{
    case InitialGame = 1; // ★から 1 枚、★★から 1 枚
    case CubeAndOther = 2; // 通信確立系から 1 枚、それ以外から 1 枚
    case Random = 3; // 完全ランダムに 2 枚
}
