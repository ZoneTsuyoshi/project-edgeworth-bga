<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Board;

use Bga\Games\ProjectEdgeworth\Material\MissionCards;

/**
 * ミッションカード 10 枚（M001〜M010）の形状（003 §8.1）。
 * 独自のデータは持たず、Material\MissionCards（002 §8、アキシャル座標 (q, r)）から
 * ダブルド座標 (row, x) = (r, 2q + r) に変換して作る。
 */
final class MissionShapes
{
    private function __construct()
    {
    }

    /** @return array<int, MissionShape> ミッション番号（1〜10）をキーにする */
    public static function all(): array
    {
        static $shapes = null;
        if ($shapes === null) {
            $shapes = [];
            $toDouble = static fn(array $cells): array => array_map(
                static fn(array $c): array => [$c[1], 2 * $c[0] + $c[1]],
                $cells,
            );
            foreach (MissionCards::all() as $card) {
                $number = (int) substr($card->id, 1);
                $shapes[$number] = new MissionShape(
                    $number,
                    $card->name,
                    $toDouble($card->easyCells),
                    $toDouble($card->extraCells),
                    $card->fameEasy,
                    $card->fameFull,
                );
            }
        }
        return $shapes;
    }

    public static function get(int $missionId): MissionShape
    {
        return self::all()[$missionId] ?? throw new \InvalidArgumentException("Unknown mission: {$missionId}");
    }
}
