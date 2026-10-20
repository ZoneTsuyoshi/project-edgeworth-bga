<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Scoring;

/**
 * 褒賞カード 1 枚の順位と名声（001 §12-2、FAQ 行 76〜78）。
 */
final class RewardRanking
{
    private function __construct()
    {
    }

    /**
     * 比較値の大きい順に順位ごとの名声を与える。同順位は該当順位の名声の合計を人数で割って切り捨て、比較値 0 は名声なし。
     *
     * @param array<int, int> $values プレイヤー ID => 比較値
     * @param list<int> $points 1 位から順の名声（カードの 18, 12, 6, 0）
     * @return array<int, array{rank: int, points: int}> プレイヤー ID => 同順位の分け合い後の順位（1 始まり）と名声
     */
    public static function award(array $values, array $points): array
    {
        $groups = [];
        foreach ($values as $playerId => $value) {
            $groups[$value][] = $playerId;
        }
        krsort($groups);

        $result = [];
        $position = 0;
        foreach ($groups as $value => $playerIds) {
            $count = count($playerIds);
            $sum = 0;
            for ($i = $position; $i < $position + $count; $i++) {
                $sum += $points[$i] ?? 0;
            }
            $each = $value > 0 ? intdiv($sum, $count) : 0;
            foreach ($playerIds as $playerId) {
                $result[$playerId] = ['rank' => $position + 1, 'points' => $each];
            }
            $position += $count;
        }
        return $result;
    }
}
