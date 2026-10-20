<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 褒賞カード 24 面（T001〜T024）の定義（002 §7）。両面の対応は 002 の未確定事項のため持たない。
 */
final class RewardCards
{
    /** @var array<string, array{string, string, string, string, string, string, string, list<int>}> */
    private const ROWS = [
        'T001' => [
            '海上覇権', 'Sea Hegemony', 'sea_hegemony',
            '海区域の通信確立数',
            'Number of linked [Sea] areas',
            'CUBE', 'STAR1', [18, 12, 6, 0],
        ],
        'T002' => [
            '大陸覇権', 'Land Hegemony', 'land_hegemony',
            '陸区域の通信確立数',
            'Number of linked [Land] areas',
            'CUBE', 'STAR1', [18, 12, 6, 0],
        ],
        'T003' => [
            '独立覇権', 'Independent Hegemony', 'independent_hegemony',
            '非連結な通信確立済み区域のまとまりの数',
            'Number of separate groups of linked areas',
            'CUBE', 'NONE', [18, 12, 6, 0],
        ],
        'T004' => [
            '連鎖覇権', 'Chain Hegemony', 'chain_hegemony',
            '通信確立済み区域の最大連結数',
            'Largest number of connected linked areas',
            'CUBE', 'NONE', [18, 12, 6, 0],
        ],
        'T005' => [
            '欧阿太平覇権', 'Euro-Africa Pacific Hegemony', 'euro_africa_pacific_hegemony',
            '地域[A][C][E][G]の通信確立数',
            'Number of links in regions [A][C][E][G]',
            'CUBE', 'NONE', [18, 12, 6, 0],
        ],
        'T006' => [
            '露亜南北覇権', 'Russia-Asia North-South Hegemony', 'russia_asia_north_south_hegemony',
            '地域[B][D][F][H]の通信確立数',
            'Number of links in regions [B][D][F][H]',
            'CUBE', 'NONE', [18, 12, 6, 0],
        ],
        'T007' => [
            '虎視覇権', 'Watchful Tiger Hegemony', 'watchful_tiger_hegemony',
            '2番目以降に通信確立済み区域数',
            'Number of linked areas that you were not the first to link',
            'CUBE', 'NONE', [18, 12, 6, 0],
        ],
        'T008' => [
            '要塞覇権', 'Fortress Hegemony', 'fortress_hegemony',
            '拠点に隣接した区域の通信確立済み区域数',
            'Number of linked areas adjacent to a base',
            'CUBE', 'NONE', [18, 12, 6, 0],
        ],
        'T009' => [
            '連隊覇権', 'Regiment Hegemony', 'regiment_hegemony',
            '[CREW]のプロジェクトカードの配備数',
            'Number of deployed [CREW] Project cards',
            'OTHER', 'STAR2', [18, 12, 6, 0],
        ],
        'T010' => [
            '駆動覇権', 'Drive Hegemony', 'drive_hegemony',
            '[DEVICE]のプロジェクトカードの配備数',
            'Number of deployed [DEVICE] Project cards',
            'OTHER', 'STAR2', [18, 12, 6, 0],
        ],
        'T011' => [
            '電脳覇権', 'Cyber Hegemony', 'cyber_hegemony',
            '[SOFTWARE]のプロジェクトカードの配備数',
            'Number of deployed [SOFTWARE] Project cards',
            'OTHER', 'STAR2', [18, 12, 6, 0],
        ],
        'T012' => [
            '軌道覇権', 'Orbit Hegemony', 'orbit_hegemony',
            '[CREW][DEVICE][SOFTWARE]のプロジェクトカードが配備されている衛星数',
            'Number of satellites with [CREW], [DEVICE] and [SOFTWARE] Project cards all deployed',
            'OTHER', 'NONE', [18, 12, 6, 0],
        ],
        'T013' => [
            '星脈覇権', 'Star Vein Hegemony', 'star_vein_hegemony',
            '予算値',
            'Budget value',
            'OTHER', 'NONE', [18, 12, 6, 0],
        ],
        'T014' => [
            '摩耶覇権', 'Maya Hegemony', 'maya_hegemony',
            '所持[マヤ]合計',
            'Total [Maya] held',
            'OTHER', 'NONE', [18, 12, 6, 0],
        ],
        'T015' => [
            '火焔覇権', 'Flame Hegemony', 'flame_hegemony',
            '稼働中の衛星の残燃料の合計値',
            'Total remaining fuel of your active satellites',
            'OTHER', 'NONE', [18, 12, 6, 0],
        ],
        'T016' => [
            '業魔覇権', 'Fiend Hegemony', 'fiend_hegemony',
            '特定の衛星の最大積載燃料',
            'Maximum loaded fuel of a single satellite',
            'OTHER', 'NONE', [18, 12, 6, 0],
        ],
        'T017' => [
            '平原覇権', 'Plains Hegemony', 'plains_hegemony',
            '標準陸区域・森林区域の通信確立数',
            'Number of linked Standard Land and Forest areas',
            'CUBE', 'ADVANCED_ONLY', [18, 12, 6, 0],
        ],
        'T018' => [
            '夕凪覇権', 'Evening Calm Hegemony', 'evening_calm_hegemony',
            '標準海区域の通信確立数',
            'Number of linked Standard Sea areas',
            'CUBE', 'ADVANCED_ONLY', [18, 12, 6, 0],
        ],
        'T019' => [
            '山麓覇権', 'Foothills Hegemony', 'foothills_hegemony',
            '山岳区域に隣接した海区域の通信確立数',
            'Number of linked [Sea] areas adjacent to a Mountain area',
            'CUBE', 'ADVANCED_ONLY', [18, 12, 6, 0],
        ],
        'T020' => [
            '氷霧覇権', 'Ice Mist Hegemony', 'ice_mist_hegemony',
            '雪原区域に隣接した海区域の通信確立数',
            'Number of linked [Sea] areas adjacent to a Snowfield area',
            'CUBE', 'ADVANCED_ONLY', [18, 12, 6, 0],
        ],
        'T021' => [
            '植栽覇権', 'Planting Hegemony', 'planting_hegemony',
            '森林区域に隣接した海区域の通信確立数',
            'Number of linked [Sea] areas adjacent to a Forest area',
            'CUBE', 'ADVANCED_ONLY', [18, 12, 6, 0],
        ],
        'T022' => [
            '神域覇権', 'Sacred Realm Hegemony', 'sacred_realm_hegemony',
            '連続した通信確立済み区域における区域種数の最大',
            'Largest number of area types within a connected group of linked areas',
            'CUBE', 'ADVANCED_ONLY', [18, 12, 6, 0],
        ],
        'T023' => [
            '深淵覇権', 'Abyss Hegemony', 'abyss_hegemony',
            '深海区域に隣接した海区域の通信確立数',
            'Number of linked [Sea] areas adjacent to a Deep Sea area',
            'CUBE', 'ADVANCED_ONLY', [18, 12, 6, 0],
        ],
        'T024' => [
            '常盤覇権', 'Evergreen Hegemony', 'evergreen_hegemony',
            '岩礁区域に隣接した陸区域の通信確立数',
            'Number of linked [Land] areas adjacent to a Reef area',
            'CUBE', 'ADVANCED_ONLY', [18, 12, 6, 0],
        ],
    ];

    /** @var array<string, RewardCardDefinition>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, RewardCardDefinition> カード ID => 定義（ID 順）
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            $cards = [];
            foreach (self::ROWS as $id => $row) {
                $cards[$id] = new RewardCardDefinition(
                    $id,
                    $row[0],
                    $row[1],
                    $row[2],
                    $row[3],
                    $row[4],
                    RewardClass::from($row[5]),
                    RewardMark::from($row[6]),
                    $row[7],
                );
            }
            self::$cache = $cards;
        }

        return self::$cache;
    }

    public static function get(string $id): RewardCardDefinition
    {
        return self::all()[$id] ?? throw new \OutOfBoundsException("未定義のカード ID: {$id}");
    }
}
