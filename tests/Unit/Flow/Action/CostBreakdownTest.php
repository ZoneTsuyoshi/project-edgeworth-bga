<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Flow\Action;

use Bga\Games\projectedgeworth\Flow\Action\DeployCost;
use Bga\Games\projectedgeworth\Flow\Action\LinkCost;
use PHPUnit\Framework\TestCase;

final class CostBreakdownTest extends TestCase
{
    public function testDeployCostAddsSurchargeBeforeSubtractingAndFloorsAtZero(): void
    {
        $this->assertSame(10, (new DeployCost(5, 5, 0))->total());
        $this->assertSame(2, (new DeployCost(3, 5, 6))->total());
        $this->assertSame(0, (new DeployCost(3, 0, 6))->total());
        $this->assertSame(0, (new DeployCost(0, 0, 0))->total());
    }

    public function testLinkCostTotalsAreaPioneerAndSpecial(): void
    {
        $cost = new LinkCost(5, 1, 0, 4, [['to' => 1, 'amount' => 3], ['to' => 2, 'amount' => 3]], 0);
        $this->assertSame(6, $cost->pioneerTotal());
        $this->assertSame(10, $cost->total());

        $special = new LinkCost(2, 0, 99, 0, [], LinkCost::SPECIAL_COST);
        $this->assertSame(5, $special->total());
    }

    public function testConstants(): void
    {
        $this->assertSame(3, LinkCost::PIONEER_UNIT);
        $this->assertSame(5, LinkCost::SPECIAL_COST);
    }
}
