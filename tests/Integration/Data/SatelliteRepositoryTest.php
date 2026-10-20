<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Data;

use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Data\SatelliteRepository;
use Bga\Games\projectedgeworth\Material\SatelliteColor;
use Bga\Games\projectedgeworth\Material\SatellitePlan;
use Bga\Games\projectedgeworth\Material\SatelliteShape;
use ProjectEdgeworthTests\Support\DatabaseTestCase;

final class SatelliteRepositoryTest extends DatabaseTestCase
{
    private SatelliteRepository $sats;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sats = new SatelliteRepository($this->db);
    }

    public function testCreateStoresEnumsAsSpecifiedIntegers(): void
    {
        $sat = $this->sats->create(10, SatelliteColor::Red, SatelliteShape::Concorde, SatellitePlan::Europa, 'R018');
        $this->assertSame(SatelliteColor::Red, $sat->color);
        $this->assertSame(SatelliteShape::Concorde, $sat->shape);
        $this->assertSame(SatellitePlan::Europa, $sat->plan);
        $this->assertFalse($sat->isActive());
        $this->assertNull($sat->direction);
        $row = $this->db->rows("SELECT `sat_color`, `sat_shape`, `sat_plan` FROM `satellite` WHERE `sat_id` = {$sat->id}")[0];
        $this->assertSame(['2', '3', '3'], [(string) $row['sat_color'], (string) $row['sat_shape'], (string) $row['sat_plan']]);
    }

    public function testOwnerShapeIsUnique(): void
    {
        $this->sats->create(10, SatelliteColor::Blue, SatelliteShape::Hammer, SatellitePlan::Moon, 'R001B');
        $this->expectException(\PDOException::class);
        $this->sats->create(10, SatelliteColor::Blue, SatelliteShape::Hammer, SatellitePlan::Moon, 'R001B');
    }

    public function testLaunchMoveTurnAndDeactivateKeepCellAndDirectionTogether(): void
    {
        $sat = $this->sats->create(10, SatelliteColor::Blue, SatelliteShape::Hammer, SatellitePlan::Moon, 'R001B');
        $this->sats->launch($sat->id, 30, Direction::NE);
        $active = $this->sats->get($sat->id);
        $this->assertTrue($active->isActive());
        $this->assertSame(30, $active->cell);
        $this->assertSame(Direction::NE, $active->direction);

        $this->sats->move($sat->id, 31, Direction::SE);
        $this->sats->turn($sat->id, Direction::SW);
        $moved = $this->sats->get($sat->id);
        $this->assertSame([31, Direction::SW], [$moved->cell, $moved->direction]);
        $this->assertSame([$sat->id], array_map(static fn($s): int => $s->id, $this->sats->activeAtCell(31)));

        $this->sats->addFuelUsed($sat->id, 2, 5);
        $this->sats->deactivate($sat->id);
        $off = $this->sats->get($sat->id);
        $this->assertNull($off->cell);
        $this->assertNull($off->direction);
        $this->assertSame(0, $off->fuelUsed);
    }

    public function testInvalidTransitionsThrow(): void
    {
        $sat = $this->sats->create(10, SatelliteColor::Blue, SatelliteShape::Hammer, SatellitePlan::Moon, 'R001B');
        try {
            $this->sats->move($sat->id, 3, Direction::NE);
            $this->fail('inactive satellite cannot move');
        } catch (\DomainException) {
        }
        try {
            $this->sats->launch($sat->id, 3, Direction::E);
            $this->fail('E is not a satellite direction');
        } catch (\InvalidArgumentException) {
        }
        $this->sats->launch($sat->id, 3, Direction::NE);
        $this->expectException(\DomainException::class);
        $this->sats->launch($sat->id, 4, Direction::NE);
    }

    public function testFuelIsClampedBetweenZeroAndCapacityPlusOne(): void
    {
        $sat = $this->sats->create(10, SatelliteColor::Blue, SatelliteShape::Hammer, SatellitePlan::Moon, 'R001B');
        $this->sats->launch($sat->id, 3, Direction::NE);
        $this->assertSame(4, $this->sats->addFuelUsed($sat->id, 4, 5));
        $this->assertSame(6, $this->sats->addFuelUsed($sat->id, 10, 5));
        $this->assertSame(0, $this->sats->addFuelUsed($sat->id, -10, 5));
    }

    public function testSwapExchangesPositionDirectionAndFuel(): void
    {
        $a = $this->sats->create(10, SatelliteColor::Blue, SatelliteShape::Hammer, SatellitePlan::Moon, 'R001B');
        $b = $this->sats->create(11, SatelliteColor::Green, SatelliteShape::Hammer, SatellitePlan::Moon, 'R001G');
        $this->sats->launch($a->id, 7, Direction::NW);
        $this->sats->addFuelUsed($a->id, 3, 9);

        $this->sats->swapPositions($a->id, $b->id);
        $a2 = $this->sats->get($a->id);
        $b2 = $this->sats->get($b->id);
        $this->assertFalse($a2->isActive());
        $this->assertNull($a2->direction);
        $this->assertSame(0, $a2->fuelUsed);
        $this->assertSame([7, Direction::NW, 3], [$b2->cell, $b2->direction, $b2->fuelUsed]);
    }

    public function testGraceActivationAndMemo(): void
    {
        $a = $this->sats->create(10, SatelliteColor::Blue, SatelliteShape::Hammer, SatellitePlan::Moon, 'R001B');
        $b = $this->sats->create(10, SatelliteColor::Blue, SatelliteShape::Retro, SatellitePlan::Moon, 'R002B');
        $this->sats->setGrace($a->id, true);
        $this->sats->setGrace($b->id, true);
        $this->assertTrue($this->sats->get($a->id)->grace);
        $this->sats->clearAllGrace();
        $this->assertFalse($this->sats->get($a->id)->grace);
        $this->assertFalse($this->sats->get($b->id)->grace);

        $this->sats->setActivation($a->id, 1);
        $this->assertSame(1, $this->sats->get($a->id)->activation);
        $this->sats->resetActivations();
        $this->assertSame(0, $this->sats->get($a->id)->activation);

        $this->sats->setMemo($a->id, ['x' => 1]);
        $this->assertSame(['x' => 1], $this->sats->memo($a->id));
    }

    public function testForPlayerIsOrderedByShape(): void
    {
        $this->sats->create(10, SatelliteColor::Blue, SatelliteShape::Stealth, SatellitePlan::Moon, 'R005B');
        $this->sats->create(10, SatelliteColor::Blue, SatelliteShape::Hammer, SatellitePlan::Moon, 'R001B');
        $this->sats->create(11, SatelliteColor::Red, SatelliteShape::Retro, SatellitePlan::Moon, 'R002R');
        $shapes = array_map(static fn($s): SatelliteShape => $s->shape, $this->sats->forPlayer(10));
        $this->assertSame([SatelliteShape::Hammer, SatelliteShape::Stealth], $shapes);
        $this->assertCount(3, $this->sats->all());
    }
}
