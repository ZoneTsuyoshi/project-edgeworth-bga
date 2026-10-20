<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 褒賞の目印（002 §3.6・§7.1）。
 */
enum RewardMark: string
{
    case Star1 = 'STAR1'; // ★
    case Star2 = 'STAR2'; // ★★
    case AdvancedOnly = 'ADVANCED_ONLY'; // ✕。地域ボード裏面のゲームのみ
    case None = 'NONE'; // 無印
}
