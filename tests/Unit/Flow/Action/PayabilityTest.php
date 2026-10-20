<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Flow\Action;

use Bga\Games\projectedgeworth\Flow\Action\Payability;
use PHPUnit\Framework\TestCase;

final class PayabilityTest extends TestCase
{
    public function testEnoughMayaNeedsNoSales(): void
    {
        $p = new Payability(5, 5, 3, 0);
        $this->assertTrue($p->canPayNow());
        $this->assertSame(0, $p->shortfall());
        $this->assertSame(0, $p->minSales());
        $this->assertTrue($p->canPayWithSales());
    }

    public function testMinimumSalesRoundUp(): void
    {
        $this->assertSame(1, (new Payability(5, 2, 3, 4))->minSales());
        $this->assertSame(2, (new Payability(5, 1, 3, 4))->minSales());
        $this->assertSame(3, (new Payability(10, 1, 3, 4))->minSales());
    }

    public function testUnreachableWhenHandIsTooSmallOrPriceIsZero(): void
    {
        $this->assertNull((new Payability(10, 1, 3, 2))->minSales());
        $this->assertFalse((new Payability(10, 1, 3, 2))->canPayWithSales());
        $this->assertNull((new Payability(1, 0, 0, 5))->minSales());
    }

    public function testHigherPriceReducesTheNumberOfSales(): void
    {
        $this->assertSame(1, (new Payability(5, 0, 5, 3))->minSales());
    }
}
