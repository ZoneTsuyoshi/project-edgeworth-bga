<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Board;

use Bga\Games\projectedgeworth\Board\Direction;
use PHPUnit\Framework\TestCase;

final class DirectionTest extends TestCase
{
    public function testDeltas(): void
    {
        $expected = ['E' => [2, 0], 'W' => [-2, 0], 'NE' => [1, -1], 'NW' => [-1, -1], 'SE' => [1, 1], 'SW' => [-1, 1]];
        foreach (Direction::cases() as $d) {
            self::assertSame($expected[$d->value], [$d->dx(), $d->dy()]);
        }
    }

    public function testOppositeAndFlip(): void
    {
        self::assertSame(Direction::SW, Direction::NE->opposite());
        self::assertSame(Direction::SE, Direction::NW->opposite());
        self::assertSame(Direction::W, Direction::E->opposite());
        self::assertSame(Direction::SE, Direction::NE->flipVertical());
        self::assertSame(Direction::NW, Direction::SW->flipVertical());
        self::assertSame(Direction::E, Direction::E->flipVertical());
    }

    public function testRotationCycle(): void
    {
        $d = Direction::E;
        $seq = [];
        for ($i = 0; $i < 6; $i++) {
            $d = $d->rotateClockwise();
            $seq[] = $d->value;
        }
        self::assertSame(['SE', 'SW', 'W', 'NW', 'NE', 'E'], $seq);
    }

    /** 衛星の向きは 6 方向すべて（003 §4.1。作者回答 2026-10-20）。E・W は上下の反射を受けない */
    public function testEastWestAreNeverFlippedAndAllSixAreSatelliteHeadings(): void
    {
        self::assertCount(6, Direction::cases());
        self::assertSame(0, Direction::E->dy());
        self::assertSame(0, Direction::W->dy());
        self::assertSame(Direction::W, Direction::W->flipVertical());
    }
}
