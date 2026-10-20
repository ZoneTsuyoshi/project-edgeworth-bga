<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/**
 * 地域ボード 16 面の区域配置（003 §3.3・§3.4）。ローカル座標 (lr, lc) は 0〜2。
 */
final class RegionBoards
{
    public const IDS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    /** 表面: S = 海、L = 陸 */
    private const STANDARD = [
        'A' => ['SSL', 'SLL', 'LLL'],
        'B' => ['LSS', 'LLS', 'LLS'],
        'C' => ['SSS', 'SLL', 'SSL'],
        'D' => ['SLL', 'LSS', 'LSS'],
        'E' => ['LLS', 'SLS', 'SSS'],
        'F' => ['LLS', 'SSL', 'SSS'],
        'G' => ['SSS', 'SSS', 'SSS'],
        'H' => ['LSS', 'LLS', 'LSS'],
    ];

    /** 裏面: s 標準海 d 深海 r 岩礁 l 標準陸 f 森林 m 山岳 w 雪原 */
    private const ADVANCED = [
        'A' => ['srw', 'rff', 'lml'],
        'B' => ['wrs', 'mfr', 'mlr'],
        'C' => ['sss', 'swm', 'drl'],
        'D' => ['rww', 'lrd', 'frs'],
        'E' => ['mlr', 'rmr', 'srs'],
        'F' => ['mfr', 'rrl', 'sss'],
        'G' => ['ddr', 'rdr', 'wsr'],
        'H' => ['frd', 'ffr', 'wrs'],
    ];

    private function __construct()
    {
    }

    /** α（A〜D）か β（E〜H）か。α は true。 */
    public static function isAlpha(string $boardId): bool
    {
        return in_array($boardId, ['A', 'B', 'C', 'D'], true);
    }

    public static function areaType(BoardFace $face, string $boardId, int $lr, int $lc): AreaType
    {
        if ($lr < 0 || $lr > 2 || $lc < 0 || $lc > 2 || !isset(self::STANDARD[$boardId])) {
            throw new \InvalidArgumentException("Invalid region cell: {$boardId} ({$lr}, {$lc})");
        }
        if ($face === BoardFace::STANDARD) {
            return self::STANDARD[$boardId][$lr][$lc] === 'S' ? AreaType::SEA : AreaType::LAND;
        }
        return match (self::ADVANCED[$boardId][$lr][$lc]) {
            's' => AreaType::SEA,
            'd' => AreaType::DEEP_SEA,
            'r' => AreaType::REEF,
            'l' => AreaType::LAND,
            'f' => AreaType::FOREST,
            'm' => AreaType::MOUNTAIN,
            default => AreaType::SNOW,
        };
    }
}
