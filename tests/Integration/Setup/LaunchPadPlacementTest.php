<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Setup;

use Bga\Games\ProjectEdgeworth\Board\Adjacency;
use Bga\Games\ProjectEdgeworth\Data\PlayerValues;
use Bga\Games\ProjectEdgeworth\Data\RegionRepository;
use Bga\Games\ProjectEdgeworth\Setup\LaunchPadPlacement;
use ProjectEdgeworthTests\Support\SeededRandom;

final class LaunchPadPlacementTest extends SetupTestCase
{
    public function testPlaceableCellsAreEmptyLandCellsOnly(): void
    {
        $layout = $this->runSetup(2, [100 => 4]);
        $all = LaunchPadPlacement::placeableCells($layout, []);
        $this->assertNotSame([], $all);
        foreach ($all as $cell) {
            $this->assertTrue($layout->areaType($cell)->isLand());
        }
        $rest = LaunchPadPlacement::placeableCells($layout, [$all[0], $all[3]]);
        $this->assertCount(count($all) - 2, $rest);
        $this->assertNotContains($all[0], $rest);
        $this->assertNotContains($all[3], $rest);
    }

    public function testLayoutRestoredFromDatabaseGivesTheSamePlaceableCells(): void
    {
        $layout = $this->runSetup(3, [100 => 6], 8);
        $restored = LaunchPadPlacement::loadLayout(new RegionRepository($this->db), $this->globals);
        $this->assertSame(
            LaunchPadPlacement::placeableCells($layout, []),
            LaunchPadPlacement::placeableCells($restored, []),
        );
    }

    public function testNextPlayerFollowsSeatOrder(): void
    {
        $this->runSetup(3);
        $values = new PlayerValues($this->db);
        $this->assertSame(101, LaunchPadPlacement::nextPlayer($values));
        $values->setPadCell(101, 3);
        $this->assertSame(102, LaunchPadPlacement::nextPlayer($values));
        $values->setPadCell(102, 4);
        $values->setPadCell(103, 5);
        $this->assertNull(LaunchPadPlacement::nextPlayer($values));
    }

    public function testZombieCandidatesMaximiseDistanceFromExistingBases(): void
    {
        $layout = $this->runSetup(2);
        $placeable = LaunchPadPlacement::placeableCells($layout, []);
        // 1 人目: 空き陸区域のすべてが候補
        $this->assertSame($placeable, LaunchPadPlacement::zombieCandidates($layout, []));

        $first = $placeable[0];
        $candidates = LaunchPadPlacement::zombieCandidates($layout, [$first]);
        $this->assertNotSame([], $candidates);
        $geometry = $layout->geometry();
        $best = 0;
        foreach (LaunchPadPlacement::placeableCells($layout, [$first]) as $cell) {
            $best = max($best, Adjacency::distanceBetweenCells($geometry, $cell, $first));
        }
        foreach ($candidates as $cell) {
            $this->assertSame($best, Adjacency::distanceBetweenCells($geometry, $cell, $first));
            $this->assertContains($cell, $placeable);
            $this->assertNotSame($first, $cell);
        }
    }

    public function testZombieChoiceIsOneOfTheCandidatesAndReproducible(): void
    {
        $layout = $this->runSetup(2);
        $candidates = LaunchPadPlacement::zombieCandidates($layout, [0]);
        $a = LaunchPadPlacement::zombieChoice($layout, [0], new SeededRandom(3));
        $b = LaunchPadPlacement::zombieChoice($layout, [0], new SeededRandom(3));
        $this->assertContains($a, $candidates);
        $this->assertSame($a, $b);
    }
}
