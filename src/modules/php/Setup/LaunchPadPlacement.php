<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Setup;

use Bga\Games\ProjectEdgeworth\Board\BoardGeometry;
use Bga\Games\ProjectEdgeworth\Board\BoardLayout;
use Bga\Games\ProjectEdgeworth\Board\LaunchSiteSelector;
use Bga\Games\ProjectEdgeworth\Board\RandomSource;
use Bga\Games\ProjectEdgeworth\Data\GlobalValues;
use Bga\Games\ProjectEdgeworth\Data\PlayerValues;
use Bga\Games\ProjectEdgeworth\Data\RegionRepository;

/**
 * 発射拠点の配置に必要な問い合わせ（001 §5 手順 10、009 §3.3・§9.2）。配置そのもの（状態 10・11）は I-07 以降。
 */
final class LaunchPadPlacement
{
    private function __construct()
    {
    }

    /**
     * 保存済みの region と board_face から盤面を復元する。
     */
    public static function loadLayout(RegionRepository $regions, GlobalValues $globals): BoardLayout
    {
        $placements = [];
        foreach ($regions->all() as $r) {
            $placements[$r['row']][$r['col']] = ['boardId' => $r['board'], 'flip' => $r['flipped']];
        }
        ksort($placements);
        foreach ($placements as &$row) {
            ksort($row);
        }
        unset($row);
        $geometry = new BoardGeometry($globals->boardCols(), $globals->rowShift());
        return new BoardLayout($globals->boardFace(), $geometry, $placements);
    }

    /**
     * 次に置くプレイヤー（席順で、発射拠点が未配置の最初の 1 人）。全員配置済みなら null。
     */
    public static function nextPlayer(PlayerValues $players): ?int
    {
        foreach ($players->playerIds() as $id) {
            if ($players->padCell($id) === null) {
                return $id;
            }
        }
        return null;
    }

    /**
     * 配置できる区域（まだ誰の発射拠点もない陸系の区域。区域 ID の昇順）。
     *
     * @param list<int> $occupiedCells 既に置かれた発射拠点の区域 ID
     * @return list<int>
     */
    public static function placeableCells(BoardLayout $layout, array $occupiedCells): array
    {
        return array_values(array_filter(
            $layout->cellIds(),
            static fn(int $cell): bool => $layout->areaType($cell)->isLand() && !in_array($cell, $occupiedCells, true),
        ));
    }

    /**
     * zombie の既定の候補（他の発射拠点までの最短距離が最大の区域すべて。009 §9.2）。
     *
     * @param list<int> $occupiedCells
     * @return list<int>
     */
    public static function zombieCandidates(BoardLayout $layout, array $occupiedCells): array
    {
        return LaunchSiteSelector::candidates($layout, $occupiedCells);
    }

    /**
     * zombie の既定の区域（候補からランダムに 1 つ）。空き陸区域がなければ null。
     *
     * @param list<int> $occupiedCells
     */
    public static function zombieChoice(BoardLayout $layout, array $occupiedCells, RandomSource $rng): ?int
    {
        return LaunchSiteSelector::choose($layout, $occupiedCells, $rng);
    }
}
