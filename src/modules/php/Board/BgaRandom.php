<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/**
 * BGA の乱数 bga_rand を包む実装（004 §5。本番のゲーム内の乱数はこれを使う。リプレイ・検証のため）。
 * bga_rand は BGA のグローバル関数で、PHPUnit の環境には無い。テストでは固定列の RandomSource を使う。
 */
final class BgaRandom implements RandomSource
{
    public function rand(int $min, int $max): int
    {
        return bga_rand($min, $max);
    }
}
