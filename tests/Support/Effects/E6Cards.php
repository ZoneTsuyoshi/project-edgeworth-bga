<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support\Effects;

use Bga\Games\projectedgeworth\Material\ProjectCards;

/**
 * 011 §8 の E-6（通信確立）14 種のカード。すべてプロジェクトカード。
 */
final class E6Cards
{
    public const PROJECT_IDS = ['P047', 'P050', 'P051', 'P056', 'P057', 'P058', 'P059', 'P060', 'P061', 'P076', 'P096', 'P097', 'P098', 'P099'];

    /**
     * @return list<string> 14 種の `name_id`
     */
    public static function keys(): array
    {
        return array_map(static fn(string $id): string => ProjectCards::get($id)->nameId, self::PROJECT_IDS);
    }

    private function __construct()
    {
    }
}
