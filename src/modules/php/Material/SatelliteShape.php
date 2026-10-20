<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 衛星の形状（002 §3.1・§3.6）。
 */
enum SatelliteShape: string
{
    case Hammer = 'HAMMER';
    case Retro = 'RETRO';
    case Scallop = 'SCALLOP';
    case Concorde = 'CONCORDE';
    case Stealth = 'STEALTH';
}
