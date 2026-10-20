<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Scoring;

use Bga\Games\projectedgeworth\Scoring\RewardRanking;
use PHPUnit\Framework\TestCase;

final class RewardRankingTest extends TestCase
{
    private const POINTS = [18, 12, 6, 0];

    /**
     * @param array<int, int> $values
     * @return array<int, int> プレイヤー ID => 名声
     */
    private function points(array $values): array
    {
        return array_map(static fn(array $r): int => $r['points'], RewardRanking::award($values, self::POINTS));
    }

    public function testRulebookExample1TwoTiedForSecond(): void
    {
        // RB p.9 例 1 / FAQ 行 76: 9, 7, 7, 4 → 18, 9, 9, 0
        $this->assertSame([1 => 18, 2 => 9, 3 => 9, 4 => 0], $this->points([1 => 9, 2 => 7, 3 => 7, 4 => 4]));
    }

    public function testRulebookExample2ZeroGetsNothing(): void
    {
        // RB p.9 例 2 / FAQ 行 77: 9, 5, 0, 0 → 18, 12, 0, 0
        $this->assertSame([1 => 18, 2 => 12, 3 => 0, 4 => 0], $this->points([1 => 9, 2 => 5, 3 => 0, 4 => 0]));
    }

    public function testThreeTiedForSecond(): void
    {
        // RB p.24: (12 + 6 + 0) / 3 = 6
        $this->assertSame([1 => 18, 2 => 6, 3 => 6, 4 => 6], $this->points([1 => 5, 2 => 3, 3 => 3, 4 => 3]));
    }

    public function testTiedForFirstRoundsDown(): void
    {
        // (18 + 12) / 2 = 15, 3 位は 6
        $this->assertSame([1 => 15, 2 => 15, 3 => 6, 4 => 0], $this->points([1 => 4, 2 => 4, 3 => 2, 4 => 1]));
        // 3 人が 1 位: (18 + 12 + 6) / 3 = 12
        $this->assertSame([1 => 12, 2 => 12, 3 => 12, 4 => 0], $this->points([1 => 4, 2 => 4, 3 => 4, 4 => 1]));
        // 4 人全員: (18 + 12 + 6 + 0) / 4 = 9
        $this->assertSame([1 => 9, 2 => 9, 3 => 9, 4 => 9], $this->points([1 => 4, 2 => 4, 3 => 4, 4 => 4]));
        // 切り捨て: 2 人が 3 位 (6 + 0) / 2 = 3
        $this->assertSame([1 => 18, 2 => 12, 3 => 3, 4 => 3], $this->points([1 => 9, 2 => 8, 3 => 7, 4 => 7]));
    }

    public function testAllZeroGetsNothing(): void
    {
        $this->assertSame([1 => 0, 2 => 0], $this->points([1 => 0, 2 => 0]));
    }

    public function testTiedZeroDoesNotShareWithLowerRanks(): void
    {
        // 0 同士が 3 位・4 位で並んでも 0。1 位・2 位は通常どおり
        $this->assertSame([1 => 18, 2 => 12, 3 => 0, 4 => 0], $this->points([1 => 3, 2 => 2, 3 => 0, 4 => 0]));
    }

    public function testTwoAndThreePlayersFollowRanking(): void
    {
        // FAQ 行 78: 2 人プレイの 2 位は 12、3 人プレイの 3 位は 6
        $this->assertSame([1 => 18, 2 => 12], $this->points([1 => 5, 2 => 1]));
        $this->assertSame([1 => 18, 2 => 12, 3 => 6], $this->points([1 => 5, 2 => 3, 3 => 1]));
        // 2 人が並ぶと (18 + 12) / 2
        $this->assertSame([1 => 15, 2 => 15], $this->points([1 => 2, 2 => 2]));
    }

    public function testRankIsStartPositionOfTheTie(): void
    {
        $r = RewardRanking::award([1 => 9, 2 => 7, 3 => 7, 4 => 4], self::POINTS);
        $this->assertSame([1, 2, 2, 4], [$r[1]['rank'], $r[2]['rank'], $r[3]['rank'], $r[4]['rank']]);
    }
}
