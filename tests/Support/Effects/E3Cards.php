<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support\Effects;

use Bga\Games\ProjectEdgeworth\Material\ProjectCards;

/**
 * 011 §8 の E-3（任意の効果と問い）22 種のカード。
 */
final class E3Cards
{
    /** プロジェクトカード 12 種 */
    public const PROJECT_IDS = ['P005', 'P006', 'P019', 'P020', 'P021', 'P036', 'P068', 'P069', 'P070', 'P072', 'P086', 'P087'];

    /** 衛星カード 10 種（EUROPA R016〜R020、GANYMEDE R021〜R025） */
    public const SATELLITE_KEYS = [
        'hammer_europa', 'retro_europa', 'scallop_europa', 'concorde_europa', 'stealth_europa',
        'hammer_ganymede', 'retro_ganymede', 'scallop_ganymede', 'concorde_ganymede', 'stealth_ganymede',
    ];

    /**
     * @return list<string> 22 種の `name_id`
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
