<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 衛星の色（002 §3.1・§3.6）。
 */
enum SatelliteColor: string
{
    case Blue = 'BLUE'; // B
    case Green = 'GREEN'; // G
    case Red = 'RED'; // R
    case Yellow = 'YELLOW'; // Y
}
