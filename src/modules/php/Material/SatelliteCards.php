<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * 衛星カード 40 面（MOON 面 R001B〜R005Y の 20 面、色別プランの面 R006〜R025 の 20 面）の定義（002 §6）。
 */
final class SatelliteCards
{
    /** @var array<string, array{string, string, string, string, int, int, int, string, string, string}> */
    private const ROWS = [
        'R001B' => [
            'MOON', 'BLUE', 'HAMMER', 'hammer_moon',
            10, 5, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R001G' => [
            'MOON', 'GREEN', 'HAMMER', 'hammer_moon',
            10, 5, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R001R' => [
            'MOON', 'RED', 'HAMMER', 'hammer_moon',
            10, 5, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R001Y' => [
            'MOON', 'YELLOW', 'HAMMER', 'hammer_moon',
            10, 5, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R002B' => [
            'MOON', 'BLUE', 'RETRO', 'retro_moon',
            15, 5, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R002G' => [
            'MOON', 'GREEN', 'RETRO', 'retro_moon',
            15, 5, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R002R' => [
            'MOON', 'RED', 'RETRO', 'retro_moon',
            15, 5, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R002Y' => [
            'MOON', 'YELLOW', 'RETRO', 'retro_moon',
            15, 5, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R003B' => [
            'MOON', 'BLUE', 'SCALLOP', 'scallop_moon',
            20, 10, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R003G' => [
            'MOON', 'GREEN', 'SCALLOP', 'scallop_moon',
            20, 10, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R003R' => [
            'MOON', 'RED', 'SCALLOP', 'scallop_moon',
            20, 10, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R003Y' => [
            'MOON', 'YELLOW', 'SCALLOP', 'scallop_moon',
            20, 10, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R004B' => [
            'MOON', 'BLUE', 'CONCORDE', 'concorde_moon',
            30, 10, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R004G' => [
            'MOON', 'GREEN', 'CONCORDE', 'concorde_moon',
            30, 10, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R004R' => [
            'MOON', 'RED', 'CONCORDE', 'concorde_moon',
            30, 10, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R004Y' => [
            'MOON', 'YELLOW', 'CONCORDE', 'concorde_moon',
            30, 10, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R005B' => [
            'MOON', 'BLUE', 'STEALTH', 'stealth_moon',
            30, 15, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R005G' => [
            'MOON', 'GREEN', 'STEALTH', 'stealth_moon',
            30, 15, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R005R' => [
            'MOON', 'RED', 'STEALTH', 'stealth_moon',
            30, 15, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R005Y' => [
            'MOON', 'YELLOW', 'STEALTH', 'stealth_moon',
            30, 15, 2, 'LAUNCHED', 'ON_LAUNCH', 'ANY',
        ],
        'R006' => [
            'PHOBOS', 'BLUE', 'HAMMER', 'hammer_phobos',
            15, 5, 2, 'LAUNCHED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'R007' => [
            'PHOBOS', 'BLUE', 'RETRO', 'retro_phobos',
            20, 10, 2, 'DEPLOYED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'R008' => [
            'PHOBOS', 'BLUE', 'SCALLOP', 'scallop_phobos',
            20, 10, 2, 'LINKED', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'R009' => [
            'PHOBOS', 'BLUE', 'CONCORDE', 'concorde_phobos',
            20, 10, 2, 'ANYTIME', 'CONSTANT', 'ACTIVE_ONLY',
        ],
        'R010' => [
            'PHOBOS', 'BLUE', 'STEALTH', 'stealth_phobos',
            30, 10, 2, 'BUDGET_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R011' => [
            'DEIMOS', 'GREEN', 'HAMMER', 'hammer_deimos',
            15, 5, 2, 'PROPOSAL_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R012' => [
            'DEIMOS', 'GREEN', 'RETRO', 'retro_deimos',
            20, 10, 2, 'LAUNCHED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R013' => [
            'DEIMOS', 'GREEN', 'SCALLOP', 'scallop_deimos',
            20, 10, 2, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R014' => [
            'DEIMOS', 'GREEN', 'CONCORDE', 'concorde_deimos',
            20, 10, 2, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R015' => [
            'DEIMOS', 'GREEN', 'STEALTH', 'stealth_deimos',
            30, 10, 2, 'BUDGET_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R016' => [
            'EUROPA', 'RED', 'HAMMER', 'hammer_europa',
            15, 5, 2, 'PROPOSAL_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R017' => [
            'EUROPA', 'RED', 'RETRO', 'retro_europa',
            25, 10, 2, 'DEPLOYED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R018' => [
            'EUROPA', 'RED', 'SCALLOP', 'scallop_europa',
            30, 10, 2, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R019' => [
            'EUROPA', 'RED', 'CONCORDE', 'concorde_europa',
            30, 10, 2, 'LAUNCHED_OR_TURNED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R020' => [
            'EUROPA', 'RED', 'STEALTH', 'stealth_europa',
            35, 10, 2, 'BUDGET_PHASE', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R021' => [
            'GANYMEDE', 'YELLOW', 'HAMMER', 'hammer_ganymede',
            15, 5, 2, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R022' => [
            'GANYMEDE', 'YELLOW', 'RETRO', 'retro_ganymede',
            15, 5, 2, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R023' => [
            'GANYMEDE', 'YELLOW', 'SCALLOP', 'scallop_ganymede',
            20, 10, 2, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R024' => [
            'GANYMEDE', 'YELLOW', 'CONCORDE', 'concorde_ganymede',
            20, 10, 2, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
        'R025' => [
            'GANYMEDE', 'YELLOW', 'STEALTH', 'stealth_ganymede',
            30, 15, 2, 'LINKED', 'ACTIVATED', 'ACTIVE_ONLY',
        ],
    ];

    /** @var array<string, SatelliteCardDefinition>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, SatelliteCardDefinition> カード ID => 定義（ID 順）
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            $cards = [];
            foreach (self::ROWS as $id => $row) {
                $cards[$id] = new SatelliteCardDefinition(
                    $id,
                    SatellitePlan::from($row[0]),
                    SatelliteColor::from($row[1]),
                    SatelliteShape::from($row[2]),
                    $row[3],
                    $row[4],
                    $row[5],
                    $row[6],
                    EffectTiming::from($row[7]),
                    EffectKind::from($row[8]),
                    ActiveCondition::from($row[9]),
                    CardTexts::SATELLITE[$row[3]]['ja'],
                    CardTexts::SATELLITE[$row[3]]['en'],
                );
            }
            self::$cache = $cards;
        }

        return self::$cache;
    }

    public static function get(string $id): SatelliteCardDefinition
    {
        return self::all()[$id] ?? throw new \OutOfBoundsException("未定義のカード ID: {$id}");
    }
}
