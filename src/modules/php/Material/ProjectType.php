<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * プロジェクトのタイプ（002 §3.2）。P001〜P033 が CREW、P034〜P066 が DEVICE、P067〜P099 が SOFTWARE。
 */
enum ProjectType: string
{
    case Crew = 'CREW';
    case Device = 'DEVICE';
    case Software = 'SOFTWARE';
}
