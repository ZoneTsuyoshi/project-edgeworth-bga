<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Board;

use Bga\Games\projectedgeworth\Board\CubeSlots;
use PHPUnit\Framework\TestCase;

final class CubeSlotsTest extends TestCase
{
    public function testZOrder(): void
    {
        // 1 人目が左上、2 人目が右上、3 人目が左下、4 人目が右下（003 §5.1）
        self::assertSame(0, CubeSlots::nextSlot(0));
        self::assertSame(1, CubeSlots::nextSlot(1));
        self::assertSame(2, CubeSlots::nextSlot(2));
        self::assertSame(3, CubeSlots::nextSlot(3));
        self::assertNull(CubeSlots::nextSlot(4));
    }

    public function testStackedCubesSkipSlots(): void
    {
        // p.5 の例: 左上に 2 つ重なっている区域に置くと slot 2（左下）
        $cubesOnArea = 2;
        self::assertSame(2, CubeSlots::nextSlot($cubesOnArea));
        // 同時に 3 人が確立（slot 0 に 3 つ重ねた）→ 次の人は slot 3
        self::assertSame(3, CubeSlots::nextSlot(3));
    }
}
