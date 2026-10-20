<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Flow;

use Bga\Games\projectedgeworth\Flow\FuelMath;
use PHPUnit\Framework\TestCase;

/** 001 §4-4 と FAQ 行 13〜15・18・19。 */
final class FuelMathTest extends TestCase
{
    public function testCapacityIsSatelliteCardPlusDeployedCards(): void
    {
        // R004B（CONCORDE）の燃料庫 2 + P004 の 2 + P007 の 4（FAQ 行 18 の考え方）
        self::assertSame(2, FuelMath::capacity('R004B', []));
        self::assertSame(8, FuelMath::capacity('R004B', ['P004', 'P007']));
    }

    public function testOutOfFuelOnlyWhenUsedExceedsCapacity(): void
    {
        // FAQ 行 15: 容量 8・消費 6 で残り 2 を消費しても庫内、3 消費して初めて庫外
        self::assertFalse(FuelMath::isOutOfFuel(8, 8));
        self::assertTrue(FuelMath::isOutOfFuel(9, 8));
        self::assertSame(8, FuelMath::change(6, 2, 8));
        self::assertSame(9, FuelMath::change(6, 3, 8));
        self::assertSame(9, FuelMath::change(6, 10, 8), '庫外に出たら C + 1 で止まる');
    }

    public function testOutOfFuelDoesNotChange(): void
    {
        // FAQ 行 14: 庫外では回復しても庫内に戻らない。消費でも増えない
        self::assertSame(7, FuelMath::change(7, -2, 6));
        self::assertSame(7, FuelMath::change(7, 3, 6));
    }

    public function testRecoveryStopsAtInitialFuelTank(): void
    {
        // FAQ 行 19: 初期燃料庫より下には動かない
        self::assertSame(0, FuelMath::change(1, -5, 8));
        self::assertSame(0, FuelMath::change(0, -1, 8));
    }

    public function testCapacityIncreaseKeepsOutOfFuel(): void
    {
        // FAQ 行 13・U-6: 庫外の衛星に配備して容量が増えても庫外のまま
        self::assertSame(11, FuelMath::afterCapacityChange(7, 6, 10));
        // 庫内なら消費量はそのまま
        self::assertSame(5, FuelMath::afterCapacityChange(5, 6, 10));
        // 容量が減る場合は C + 1 を超えない
        self::assertSame(5, FuelMath::afterCapacityChange(8, 8, 4));
    }
}
