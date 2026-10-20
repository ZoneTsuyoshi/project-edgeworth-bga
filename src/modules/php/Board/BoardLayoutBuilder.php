<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/**
 * ボード配置の生成（003 §2.2〜§2.4）。乱数は注入する。
 */
final class BoardLayoutBuilder
{
    private const ALPHA = ['A', 'B', 'C', 'D'];
    private const BETA = ['E', 'F', 'G', 'H'];

    private function __construct()
    {
    }

    /**
     * @param int $boardCount 1 段のボード枚数 B（4・3・2）
     */
    public static function build(BoardFace $face, BoardPlacement $placement, int $boardCount, RandomSource $rng): BoardLayout
    {
        if ($boardCount < 2 || $boardCount > 4) {
            throw new \InvalidArgumentException("boardCount must be 2..4: {$boardCount}");
        }

        if ($placement === BoardPlacement::EARTH) {
            // 003 §2.4・未確定 No.1: 上段 A から、下段 E から B 枚。P = 0、回転なし
            $parity = 0;
            $ids = [array_slice(self::ALPHA, 0, $boardCount), array_slice(self::BETA, 0, $boardCount)];
            $flips = [array_fill(0, $boardCount, false), array_fill(0, $boardCount, false)];
        } elseif ($placement === BoardPlacement::RANDOM_NO_FLIP) {
            // 003 §2.4・未確定 No.2: 上段にするグループ（= P）を等確率で決め、各段にそのグループから B 枚
            $parity = $rng->rand(0, 1);
            $top = $parity === 0 ? self::ALPHA : self::BETA;
            $bottom = $parity === 0 ? self::BETA : self::ALPHA;
            $ids = [
                array_slice(self::shuffle($top, $rng), 0, $boardCount),
                array_slice(self::shuffle($bottom, $rng), 0, $boardCount),
            ];
            $flips = [array_fill(0, $boardCount, false), array_fill(0, $boardCount, false)];
        } else {
            // Random w/ Flip: P を等確率で決め、A〜H から 2B 枚を全区画へランダムに割り当てる
            $parity = $rng->rand(0, 1);
            $picked = array_slice(self::shuffle(RegionBoards::IDS, $rng), 0, 2 * $boardCount);
            $picked = self::shuffle($picked, $rng);
            $ids = [array_slice($picked, 0, $boardCount), array_slice($picked, $boardCount, $boardCount)];
            $flips = [[], []];
            foreach ($ids as $br => $row) {
                $needAlpha = (($br + $parity) % 2) === 0;
                foreach ($row as $id) {
                    $flips[$br][] = RegionBoards::isAlpha($id) !== $needAlpha;
                }
            }
        }

        $placements = [];
        foreach ($ids as $br => $row) {
            foreach ($row as $bc => $id) {
                $placements[$br][$bc] = ['boardId' => $id, 'flip' => $flips[$br][$bc]];
            }
        }
        return new BoardLayout($face, BoardGeometry::forBoardCount($boardCount, $parity), $placements);
    }

    /**
     * Fisher–Yates
     *
     * @param list<string> $items
     * @return list<string>
     */
    private static function shuffle(array $items, RandomSource $rng): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = $rng->rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
        return $items;
    }
}
