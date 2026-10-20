<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Board;

use Bga\Games\projectedgeworth\Board\Adjacency;
use Bga\Games\projectedgeworth\Board\BoardFace;
use Bga\Games\projectedgeworth\Board\BoardLayoutBuilder;
use Bga\Games\projectedgeworth\Board\BoardPlacement;
use Bga\Games\projectedgeworth\Board\LaunchSiteSelector;
use PHPUnit\Framework\TestCase;

/** 009 §9.2: zombie の発射拠点配置の既定 */
final class LaunchSiteSelectorTest extends TestCase
{
    public function testNoOtherSitesMeansAllFreeLand(): void
    {
        $layout = BoardLayoutBuilder::build(BoardFace::STANDARD, BoardPlacement::EARTH, 4, new SeededRandom(1));
        $land = array_values(array_filter($layout->cellIds(), static fn(int $c): bool => $layout->areaType($c)->isLand()));
        self::assertSame($land, LaunchSiteSelector::candidates($layout, []));
    }

    public function testFarthestFromExistingSites(): void
    {
        $layout = BoardLayoutBuilder::build(BoardFace::STANDARD, BoardPlacement::EARTH, 4, new SeededRandom(1));
        $g = $layout->geometry();
        $occupied = [0 * 12 + 2, 4 * 12 + 5]; // 陸区域（A の右上、F 付近）を既存の拠点とみなす
        $cands = LaunchSiteSelector::candidates($layout, $occupied);
        self::assertNotEmpty($cands);

        $minDist = static function (int $cell) use ($g, $occupied): int {
            return min(array_map(static fn(int $o): int => Adjacency::distanceBetweenCells($g, $cell, $o), $occupied));
        };
        $best = $minDist($cands[0]);
        foreach ($layout->cellIds() as $cell) {
            if (!$layout->areaType($cell)->isLand() || in_array($cell, $occupied, true)) {
                continue;
            }
            self::assertLessThanOrEqual($best, $minDist($cell));
            self::assertSame(in_array($cell, $cands, true), $minDist($cell) === $best);
        }
        foreach ($cands as $c) {
            self::assertTrue($layout->areaType($c)->isLand());
            self::assertNotContains($c, $occupied);
        }
    }

    public function testChooseUsesRandomSource(): void
    {
        $layout = BoardLayoutBuilder::build(BoardFace::STANDARD, BoardPlacement::EARTH, 4, new SeededRandom(1));
        $cands = LaunchSiteSelector::candidates($layout, [2]);
        self::assertSame($cands[0], LaunchSiteSelector::choose($layout, [2], new FixedRandom([0])));
        self::assertSame($cands[count($cands) - 1], LaunchSiteSelector::choose($layout, [2], new FixedRandom([count($cands) - 1])));
    }
}
