<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Flow;

use Bga\Games\projectedgeworth\Flow\EndCondition;
use Bga\Games\projectedgeworth\Flow\PlayerProgress;
use PHPUnit\Framework\TestCase;

/** 001 §10、009 §10。 */
final class EndConditionTest extends TestCase
{
    public function testNobodyMeetsCondition(): void
    {
        self::assertSame([], EndCondition::metBy([new PlayerProgress(1, 4, 15), new PlayerProgress(2, 0, 0)]));
    }

    public function testAllFiveSatellitesActive(): void
    {
        self::assertSame([2], EndCondition::metBy([new PlayerProgress(1, 4, 3), new PlayerProgress(2, 5, 3)]));
    }

    public function testSixteenCubes(): void
    {
        self::assertSame([1], EndCondition::metBy([new PlayerProgress(1, 0, 16), new PlayerProgress(2, 1, 15)]));
    }

    public function testBothConditionsAndMultiplePlayers(): void
    {
        // 1 人が両方、別の人が片方。IX の +3 は 1 人 1 回なので、1 人 1 件
        $met = EndCondition::metBy([
            new PlayerProgress(1, 5, 16),
            new PlayerProgress(2, 2, 16),
            new PlayerProgress(3, 4, 2),
        ]);
        self::assertSame([1, 2], $met);
    }

    public function testLandedSatelliteNoLongerCounts(): void
    {
        // FAQ 行 50: 着陸して 4 機に戻れば終了条件を満たさない（着陸後の稼働数を渡す）
        self::assertSame([], EndCondition::metBy([new PlayerProgress(1, 4, 0)]));
    }

    public function testProgressFormula(): void
    {
        self::assertSame(0, EndCondition::progress([new PlayerProgress(1, 0, 0)], 0));
        // 衛星 3 機: 0.8 × 3/5 = 0.48 → 48
        self::assertSame(48, EndCondition::progress([new PlayerProgress(1, 3, 0)], 0));
        // キューブ 8 個: 50（衛星 0.8 × 2/5 = 32 を上回る）
        self::assertSame(50, EndCondition::progress([new PlayerProgress(1, 2, 8), new PlayerProgress(2, 1, 4)], 0));
        // 最大のプレイヤーで決まる
        self::assertSame(80, EndCondition::progress([new PlayerProgress(1, 5, 0)], 0));
        // 衛星 4 機: 0.8 × 4/5 = 64
        self::assertSame(64, EndCondition::progress([new PlayerProgress(1, 4, 0)], 0));
    }

    public function testProgressCapsAt99AndIsMonotonic(): void
    {
        self::assertSame(99, EndCondition::progress([new PlayerProgress(1, 5, 16)], 0));
        // 着陸で稼働数が減っても下がらない
        self::assertSame(64, EndCondition::progress([new PlayerProgress(1, 1, 0)], 64));
    }
}
