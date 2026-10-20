<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/** 地域ボードの面。1 ゲームで混ぜない（003 §2.4）。 */
enum BoardFace: string
{
    case STANDARD = 'standard'; // 表面（A〜H）
    case ADVANCED = 'advanced'; // 裏面（RA〜RH）
}
