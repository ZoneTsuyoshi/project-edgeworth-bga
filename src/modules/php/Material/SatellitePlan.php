<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * 衛星プラン（002 §3.1・§3.6）。MOON は全員共通の表面、他は色別プランの面。
 */
enum SatellitePlan: string
{
    case Moon = 'MOON';
    case Phobos = 'PHOBOS';
    case Deimos = 'DEIMOS';
    case Europa = 'EUROPA';
    case Ganymede = 'GANYMEDE';
}
