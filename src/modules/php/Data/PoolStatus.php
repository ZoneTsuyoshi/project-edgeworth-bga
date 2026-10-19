<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Data;

/**
 * 効果のプールの項目の状態（010 §9.5）。
 */
enum PoolStatus: string
{
    /** 未解決 */
    case Pending = 'pending';
    /** [Use] / [Skip] を聞いている */
    case Asked = 'asked';
    /** 解決済み */
    case Done = 'done';
    /** [Skip] した */
    case Skipped = 'skipped';
}
