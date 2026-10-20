<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support\Effects;

use Bga\Games\ProjectEdgeworth\Material\ProjectCards;

/**
 * 011 §8 の E-4（衛星の操作）11 種のカード。
 */
final class E4Cards
{
    /** プロジェクトカード 11 種 */
    public const PROJECT_IDS = ['P004', 'P024', 'P034', 'P038', 'P044', 'P048', 'P081', 'P082', 'P090', 'P091', 'P095'];

    /**
     * @return list<string> 11 種の `name_id`
     */
    public static function keys(): array
    {
        return array_map(static fn(string $id): string => ProjectCards::get($id)->nameId, self::PROJECT_IDS);
    }

    private function __construct()
    {
    }
}
