<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/** ミッションの達成段階（002 §8、003 §8.1）。 */
enum MissionTier: string
{
    case EASY = 'easy';   // 薄い色のマスのみ
    case EXTRA = 'extra'; // 網掛けのマスまで
}
