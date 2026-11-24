<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Setup;

use Bga\Games\projectedgeworth\Setup\PackPassing;
use PHPUnit\Framework\TestCase;

final class PackPassingTest extends TestCase
{
    public function testTwoPlayersSwap(): void
    {
        $this->assertSame(102, PackPassing::receiver([101, 102], 101));
        $this->assertSame(101, PackPassing::receiver([101, 102], 102));
    }

    public function testThreeAndFourPlayersPassToTheNextSeatAndWrap(): void
    {
        $this->assertSame([102, 103, 101], array_map(static fn(int $p): int => PackPassing::receiver([101, 102, 103], $p), [101, 102, 103]));
        $this->assertSame([7, 3, 9, 5], array_map(static fn(int $p): int => PackPassing::receiver([5, 7, 3, 9], $p), [5, 7, 3, 9]));
    }

    public function testUnseatedPlayerIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PackPassing::receiver([1, 2], 3);
    }
}
