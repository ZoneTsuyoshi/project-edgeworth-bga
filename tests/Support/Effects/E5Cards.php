<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support\Effects;

use Bga\Games\ProjectEdgeworth\Material\ProjectCards;

/**
 * 011 §8 の E-5（配備）6 種のカード。
 */
final class E5Cards
{
    /** プロジェクトカード 5 種 */
    public const PROJECT_IDS = ['P011', 'P022', 'P035', 'P045', 'P074'];

    /** 衛星カード（R005 STEALTH・MOON） */
    public const SATELLITE_KEYS = ['stealth_moon'];

    /**
     * @return list<string> 6 種の `name_id`
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
