<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

/**
 * アクションカードの状態（010 §6.1）。
 */
enum ActionCardState: int
{
    /** 手札 */
    case Hand = 0;
    /** 縦（表向き。今ラウンドに選んだ） */
    case Upright = 1;
    /** 横（前ラウンドに選んだ I 以外） */
    case Sideways = 2;
}
