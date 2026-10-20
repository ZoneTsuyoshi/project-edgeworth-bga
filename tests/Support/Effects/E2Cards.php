<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support\Effects;

use Bga\Games\ProjectEdgeworth\Material\ProjectCards;

/**
 * 011 §8 の E-2（強制の反応効果）36 種のカード。
 */
final class E2Cards
{
    /** プロジェクトカード 28 種 */
    public const PROJECT_IDS = [
        'P001', 'P002', 'P003', 'P007', 'P009', 'P013', 'P023', 'P025', 'P027', 'P028', 'P029', 'P030', 'P031', 'P032',
        'P037', 'P039', 'P041', 'P042', 'P043', 'P049', 'P075', 'P078', 'P083', 'P084', 'P088', 'P089', 'P092', 'P093',
    ];

    /** 衛星カード 8 種（R001〜R004 は MOON 面で色なし、R010・R012・R013・R015） */
    public const SATELLITE_KEYS = [
        'hammer_moon', 'retro_moon', 'scallop_moon', 'concorde_moon',
        'stealth_phobos', 'retro_deimos', 'scallop_deimos', 'stealth_deimos',
    ];

    /**
     * @return list<string> 36 種の `name_id`
     */
    public static function keys(): array
    {
        return [
            ...array_map(static fn(string $id): string => ProjectCards::get($id)->nameId, self::PROJECT_IDS),
            ...self::SATELLITE_KEYS,
        ];
    }

    private function __construct()
    {
    }
}
