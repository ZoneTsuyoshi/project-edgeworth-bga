<?php

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Material;

use Bga\Games\ProjectEdgeworth\Material\EffectKind;
use Bga\Games\ProjectEdgeworth\Material\EffectTiming;
use Bga\Games\ProjectEdgeworth\Material\Region;
use PHPUnit\Framework\TestCase;

final class EnumsTest extends TestCase
{
    public function testEffectTimingHasTenValues(): void
    {
        self::assertSame(
            [
                'PLANNING_PHASE', 'BUDGET_PHASE', 'DEPLOYED', 'LAUNCHED', 'TURNED', 'LINKED',
                'PROPOSAL_PHASE', 'ORBIT_PHASE', 'ANYTIME', 'LAUNCHED_OR_TURNED',
            ],
            array_map(static fn (EffectTiming $t): string => $t->value, EffectTiming::cases()),
        );
    }

    public function testEffectKindAndRegionValues(): void
    {
        self::assertSame(
            ['CONSTANT', 'ACTIVATED', 'ON_DEPLOY', 'ON_LAUNCH'],
            array_map(static fn (EffectKind $k): string => $k->value, EffectKind::cases()),
        );
        self::assertSame(
            ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'],
            array_map(static fn (Region $r): string => $r->value, Region::cases()),
        );
    }
}
