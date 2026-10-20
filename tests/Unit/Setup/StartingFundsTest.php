<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Setup;

use Bga\Games\projectedgeworth\Board\BoardFace;
use Bga\Games\projectedgeworth\Setup\StartingFunds;
use Bga\Games\projectedgeworth\Setup\SetupPlayer;
use PHPUnit\Framework\TestCase;

final class StartingFundsTest extends TestCase
{
    public function testStandardFace(): void
    {
        $this->assertSame([30, 31, 32, 33], array_map(fn(int $s): int => StartingFunds::forSeat(BoardFace::STANDARD, $s), [0, 1, 2, 3]));
    }

    public function testAdvancedFace(): void
    {
        $this->assertSame([28, 30, 32, 34], array_map(fn(int $s): int => StartingFunds::forSeat(BoardFace::ADVANCED, $s), [0, 1, 2, 3]));
    }

    public function testSeatOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        StartingFunds::forSeat(BoardFace::STANDARD, 4);
    }

    public function testPlayerColorFromBgaHex(): void
    {
        $this->assertSame('RED', SetupPlayer::fromBgaColor(1, 'ff0000')->color->value);
        $this->assertSame('GREEN', SetupPlayer::fromBgaColor(1, '008000')->color->value);
        $this->assertSame('BLUE', SetupPlayer::fromBgaColor(1, '#0000FF')->color->value);
        $this->assertSame('YELLOW', SetupPlayer::fromBgaColor(1, 'ffa500')->color->value);
        $this->expectException(\InvalidArgumentException::class);
        SetupPlayer::fromBgaColor(1, 'ffffff');
    }
}
