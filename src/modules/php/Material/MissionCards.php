<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * ミッションカード 10 枚（M001〜M010）の定義（002 §8）。
 */
final class MissionCards
{
    /** @var array<string, array{string, string, int, int, list<array{int, int}>, list<array{int, int}>}> */
    private const ROWS = [
        'M001' => [
            'LOOP', 'loop', 5, 17,
            [[0, 0], [-1, 1], [-1, 2]],
            [[1, 0], [1, 1], [0, 2]],
        ],
        'M002' => [
            'BUMP', 'bump', 5, 18,
            [[0, 0], [-1, 1], [0, 1]],
            [[2, 0], [1, 1], [2, 1]],
        ],
        'M003' => [
            'ARCH', 'arch', 5, 18,
            [[0, 0], [0, 1], [-1, 2]],
            [[1, 0], [1, 1], [0, 2]],
        ],
        'M004' => [
            'PILE', 'pile', 5, 18,
            [[-2, 2], [-1, 2], [0, 2]],
            [[0, 0], [-1, 1], [0, 1]],
        ],
        'M005' => [
            'RECOIL', 'recoil', 8, 15,
            [[0, 0], [1, 0], [-1, 1], [1, 1]],
            [[-2, 2], [1, 2]],
        ],
        'M006' => [
            'WING', 'wing', 7, 16,
            [[-2, 1], [-1, 1], [0, 1], [1, 1]],
            [[0, 0], [-1, 2]],
        ],
        'M007' => [
            'FISH', 'fish', 9, 17,
            [[-2, 1], [-1, 1], [-3, 2], [-2, 2]],
            [[0, 0], [0, 1]],
        ],
        'M008' => [
            'EDGE', 'edge', 9, 17,
            [[0, 0], [1, 0], [-1, 1], [0, 1]],
            [[2, 0], [1, 1]],
        ],
        'M009' => [
            'LOCK', 'lock', 5, 24,
            [[-1, 1], [0, 1], [1, 1]],
            [[0, 0], [1, 0], [-1, 2], [0, 2]],
        ],
        'M010' => [
            'TURTLE', 'turtle', 5, 24,
            [[-1, 1], [0, 1], [1, 1]],
            [[0, 0], [1, 0], [-2, 2], [1, 2]],
        ],
    ];

    /** @var array<string, MissionCardDefinition>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, MissionCardDefinition> カード ID => 定義（ID 順）
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            $cards = [];
            foreach (self::ROWS as $id => $row) {
                $cards[$id] = new MissionCardDefinition(
                    $id,
                    $row[0],
                    $row[1],
                    $row[2],
                    $row[3],
                    $row[4],
                    $row[5],
                );
            }
            self::$cache = $cards;
        }

        return self::$cache;
    }

    public static function get(string $id): MissionCardDefinition
    {
        return self::all()[$id] ?? throw new \OutOfBoundsException("未定義のカード ID: {$id}");
    }
}
