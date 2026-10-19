<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Action;

use Bga\Games\ProjectEdgeworth\Data\CardLocation;
use Bga\Games\ProjectEdgeworth\Flow\Action\RuleViolation;

final class SellRulesTest extends ActionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->startGame(2);
        $this->setHand(101, [1, 2, 3]);
        $this->setMaya(101, 10);
    }

    public function testEachCardSellsForThreeMaya(): void
    {
        $gained = $this->phases->selling()->sell(101, [1, 2]);
        $this->assertSame(6, $gained);
        $this->assertSame(16, $this->maya(101));
        $this->assertSame([3], $this->handOf(101));
        $cards = $this->phases->services()->cards;
        $this->assertSame(CardLocation::Discard, $cards->locationOf(1)['location']);
    }

    public function testPriceFollowsTheSellPriceHook(): void
    {
        $this->hooks->sellExtra = 2;
        $this->assertSame(5, $this->phases->selling()->price(101));
        $this->assertSame(10, $this->phases->selling()->sell(101, [1, 2]));
    }

    public function testReservedOrForeignCardsCannotBeSold(): void
    {
        $this->setHand(102, [10]);
        foreach ([[[10], []], [[1], [1]], [[1, 1], []], [[], []]] as [$ids, $reserved]) {
            try {
                $this->phases->selling()->sell(101, $ids, $reserved);
                $this->fail('expected RuleViolation');
            } catch (RuleViolation) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(10, $this->maya(101));
    }

    public function testPayabilityCountsOnlySellableCards(): void
    {
        $this->setMaya(101, 2);
        $selling = $this->phases->selling();

        $now = $selling->assess(101, 2);
        $this->assertTrue($now->canPayNow());
        $this->assertSame(0, $now->minSales());

        $needsOne = $selling->assess(101, 5);
        $this->assertFalse($needsOne->canPayNow());
        $this->assertSame(3, $needsOne->shortfall());
        $this->assertSame(1, $needsOne->minSales());

        // 手札 3 枚 = 9 マヤ + 2 = 11 まで。選択に同梱するカード（1）は売れないので 8 まで
        $this->assertTrue($selling->assess(101, 11)->canPayWithSales());
        $this->assertFalse($selling->assess(101, 12)->canPayWithSales());
        $this->assertTrue($selling->assess(101, 8, [1])->canPayWithSales());
        $this->assertFalse($selling->assess(101, 9, [1])->canPayWithSales());
        $this->assertSame(2, $selling->assess(101, 8, [1])->minSales());
    }

    public function testTryPaySellsFirstThenPays(): void
    {
        $this->setMaya(101, 2);
        $payment = $this->phases->selling()->tryPay(101, 7, [1, 2]);
        $this->assertNotNull($payment);
        $this->assertSame(6, $payment->gainedFromSales);
        $this->assertSame(1, $this->maya(101));
        $this->assertSame([3], $this->handOf(101));
    }

    public function testTryPayDoesNothingWhenItCannotPay(): void
    {
        $this->setMaya(101, 2);
        $this->assertNull($this->phases->selling()->tryPay(101, 9, [1, 2]));
        $this->assertSame(2, $this->maya(101));
        $this->assertSame([1, 2, 3], $this->handOf(101));
        // 売却の内容が成り立たない（手札にない）ときも何もしない
        $this->assertNull($this->phases->selling()->tryPay(101, 1, [99]));
        $this->assertSame(2, $this->maya(101));
    }
}
