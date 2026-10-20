<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Flow\Action;

use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Data\Satellite;
use Bga\Games\projectedgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\projectedgeworth\Flow\Action\NoActionHooks;
use Bga\Games\projectedgeworth\Flow\Action\ProposalCounts;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Material\SatelliteColor;
use Bga\Games\projectedgeworth\Material\SatellitePlan;
use Bga\Games\projectedgeworth\Material\SatelliteShape;
use PHPUnit\Framework\TestCase;

/**
 * 既定のフックは「修正なし・禁止なし」（効果は I-12 以降）。
 */
final class NoActionHooksTest extends TestCase
{
    public function testEveryQueryReturnsTheUnmodifiedValue(): void
    {
        $hooks = new NoActionHooks();
        $sat = new Satellite(1, 101, SatelliteColor::Red, SatelliteShape::Hammer, SatellitePlan::Moon, 'R001R', 5, Direction::NE, 0, false, 0);

        $this->assertSame(0, $hooks->deployCostReduction(101, $sat, 1, 2));
        $this->assertSame(0, $hooks->launchCostReduction(101, $sat));
        $this->assertSame([], $hooks->extraLinkTargets(101, $sat, StepMode::Bonus));
        $this->assertFalse($hooks->pioneerWaived(101, $sat));
        $this->assertSame(0, $hooks->linkCostReduction(101, $sat, 5));
        foreach (ForbiddenAction::cases() as $action) {
            $this->assertFalse($hooks->isForbidden($action, $sat));
        }
        $base = new ProposalCounts(5, 2);
        $this->assertSame($base, $hooks->proposalCounts(101, true, $base));
        $this->assertSame(17, $hooks->budgetMainGain(101, 17));
        $this->assertSame(3, $hooks->sellPrice(101, 3));
    }
}
