<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 稼働条件（002 §3.5）。
 */
enum ActiveCondition: string
{
    case ActiveOnly = 'ACTIVE_ONLY'; // 稼働中のみ
    case Any = 'ANY'; // 稼働の有無に依らない
}
