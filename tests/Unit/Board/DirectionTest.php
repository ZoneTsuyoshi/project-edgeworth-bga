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

    public function testDiagonal(): void
    {
        self::assertTrue(Direction::NE->isDiagonal());
        self::assertFalse(Direction::E->isDiagonal());
    }
}
