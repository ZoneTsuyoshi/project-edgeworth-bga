<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * 褒賞の分類（002 §3.6・§7）。ゲームオプション「Random (1: Cube, 1: Other)」で使う。
 */
enum RewardClass: string
{
    case Cube = 'CUBE'; // 通信確立系
    case Other = 'OTHER'; // それ以外
}
