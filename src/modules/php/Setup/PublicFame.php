<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Setup;

/**
 * ミッション以外の名声をゲーム中に公開するかのオプション（ゲームオプション 105。004 §7、010 未確定事項 No.2）。
 */
enum PublicFame: int
{
    case Hidden = 1;
    case Visible = 2;
}
