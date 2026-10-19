<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects;

use Bga\Games\ProjectEdgeworth\Effects\Context\GameEvent;
use Bga\Games\ProjectEdgeworth\Effects\Pool\ConflictResolver;
use Bga\Games\ProjectEdgeworth\Material\CardEffects;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;
use Bga\Games\ProjectEdgeworth\Material\Hook;
use Bga\Games\ProjectEdgeworth\Material\ResourceScope;
use Bga\Games\ProjectEdgeworth\Material\ResourceSymbol;
use Bga\Games\ProjectEdgeworth\Material\ResourceTouch;
use Bga\Games\ProjectEdgeworth\Material\SatelliteShape;
use ProjectEdgeworthTests\Support\Effects\FakeGame;
use ProjectEdgeworthTests\Support\Effects\TestSpecs;

/**
 * 資源の衝突の判定（007 §6-2、011 §6-4）と、011 §11-4 の S01〜S04・S07・S08 の「衝突するか」。
 */
final class ConflictResolverTest extends EffectsTestCase
{
    /**
     * @param list<array{kind: string, src: int, spec?: EffectSpec, event?: GameEvent}> $rows
     * @return list<list<int>>
     */
    private function sets(FakeGame $game, array $rows): array
    {
        $entries = [];
        foreach ($rows as $i => $row) {
            $host = $game->host($row['kind'], $row['src']) ?? throw new \LogicException('no host');
            $spec = $row['spec'] ?? CardEffects::get($host->cardKey) ?? throw new \LogicException('no spec');
            $id = $i + 1;
            $entries[$id] = [$this->item($id, $row['kind'], $row['src'], $host->cardKey, 1, $row['event'] ?? null), $spec, $host];
        }

        return (new ConflictResolver())->sets($entries);
    }

    public function testS01BudgetNegotiatorAndStealthPhobosConflict(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Stealth, true, 'stealth_phobos');
        $game->deploy($sat, 'P006');

        // R010（M+）と P006（M- B+）は M+ と M- で衝突する → 順序を聞く
        $this->assertSame([[1, 2]], $this->sets($game, [['kind' => 'S', 'src' => $sat], ['kind' => 'P', 'src' => 6]]));
    }

    public function testS03CrewCommanderAndIntegratedPlannerDoNotConflict(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Hammer);
        $game->deploy($sat, 'P013')->deploy($sat, 'P023');

        // B+ と H+ は別の資源 → 聞かない
        $this->assertSame([], $this->sets($game, [['kind' => 'P', 'src' => 13], ['kind' => 'P', 'src' => 23]]));
    }

    public function testS02TwinDeployTechnicianAndIntegratedPlannerConflict(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Hammer);
        $game->deploy($sat, 'P011')->deploy($sat, 'P023');

        // H*（手札を見て選ぶ）と H+（引く）
        $this->assertSame([[1, 2]], $this->sets($game, [['kind' => 'P', 'src' => 11], ['kind' => 'P', 'src' => 23]]));
    }

    public function testS04AccelerationBoosterAndFuelTransferPipeConflict(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Hammer);
        $game->deploy($sat, 'P034')->deploy($sat, 'P038');
        $launched = new GameEvent(Hook::Launched, 1, $sat);

        // F+（全衛星）と F-（衛星）が重なる（007 §10 の P038 の注意）
        $this->assertSame([[1, 2]], $this->sets($game, [
            ['kind' => 'P', 'src' => 34, 'event' => $launched],
            ['kind' => 'P', 'src' => 38, 'event' => $launched],
        ]));
    }

    public function testS08SensorArrayReadsCompositionThatGambleSystemWrites(): void
    {
        $game = (new FakeGame())->player(1);
        $a = $game->satellite(1, SatelliteShape::Hammer);
        $b = $game->satellite(1, SatelliteShape::Retro);
        $game->deploy($a, 'P039')->deploy($a, 'P035')->deploy($b, 'P035');

        // 同じ衛星の Cr と C は衝突する
        $this->assertSame([[1, 2]], $this->sets($game, [['kind' => 'P', 'src' => 39], ['kind' => 'P', 'src' => 35]]));
    }

    public function testS08OnDifferentSatellitesTheyDoNotConflict(): void
    {
        $game = (new FakeGame())->player(1);
        $a = $game->satellite(1, SatelliteShape::Hammer);
        $b = $game->satellite(1, SatelliteShape::Retro);
        $game->deploy($a, 'P039')->deploy($b, 'P035');

        $this->assertSame([], $this->sets($game, [['kind' => 'P', 'src' => 39], ['kind' => 'P', 'src' => 35]]));
    }

    public function testS07FuelOnDifferentSatellitesDoNotConflictButOnTheSameOneDo(): void
    {
        $game = (new FakeGame())->player(1);
        $a = $game->satellite(1, SatelliteShape::Hammer, cardKey: 'alpha');
        $b = $game->satellite(1, SatelliteShape::Retro, cardKey: 'beta');
        $plus = TestSpecs::spec('alpha', touches: [new ResourceTouch(ResourceSymbol::FuelRecover, ResourceScope::HostSatellite)]);
        $minus = TestSpecs::spec('beta', touches: [new ResourceTouch(ResourceSymbol::FuelConsume, ResourceScope::HostSatellite)]);

        $this->assertSame([], $this->sets($game, [
            ['kind' => 'S', 'src' => $a, 'spec' => $plus],
            ['kind' => 'S', 'src' => $b, 'spec' => $minus],
        ]));

        // 同じ衛星なら F+ と F- は衝突する
        $game->deploy($a, 'P001');
        $same = $this->sets($game, [
            ['kind' => 'S', 'src' => $a, 'spec' => $plus],
            ['kind' => 'P', 'src' => 1, 'spec' => TestSpecs::spec('x', touches: $minus->touches)],
        ]);
        $this->assertSame([[1, 2]], $same);
    }

    public function testSameKindAdditionsAndReadsDoNotConflict(): void
    {
        $game = (new FakeGame())->player(1);
        $a = $game->satellite(1, SatelliteShape::Hammer, cardKey: 'alpha');
        $game->deploy($a, 'P001')->deploy($a, 'P002')->deploy($a, 'P003')->deploy($a, 'P004');
        $specs = [
            TestSpecs::spec('a', touches: [new ResourceTouch(ResourceSymbol::MayaGain)]),
            TestSpecs::spec('b', touches: [new ResourceTouch(ResourceSymbol::MayaGain)]),
            TestSpecs::spec('c', touches: [new ResourceTouch(ResourceSymbol::CompositionRead, ResourceScope::HostSatellite)]),
            TestSpecs::spec('d', touches: [new ResourceTouch(ResourceSymbol::CompositionRead, ResourceScope::HostSatellite)]),
        ];
        $rows = [];
        foreach ($specs as $i => $spec) {
            $rows[] = ['kind' => 'P', 'src' => $i + 1, 'spec' => $spec];
        }

        $this->assertSame([], $this->sets($game, $rows));
    }

    public function testConflictsChainIntoOneSet(): void
    {
        $game = (new FakeGame())->player(1);
        $a = $game->satellite(1, SatelliteShape::Hammer);
        $game->deploy($a, 'P001')->deploy($a, 'P002')->deploy($a, 'P003')->deploy($a, 'P004');
        $rows = [
            ['kind' => 'P', 'src' => 1, 'spec' => TestSpecs::spec('a', touches: [new ResourceTouch(ResourceSymbol::MayaPay)])],
            ['kind' => 'P', 'src' => 2, 'spec' => TestSpecs::spec('b', touches: [
                new ResourceTouch(ResourceSymbol::MayaGain),
                new ResourceTouch(ResourceSymbol::HandDraw),
            ])],
            ['kind' => 'P', 'src' => 3, 'spec' => TestSpecs::spec('c', touches: [new ResourceTouch(ResourceSymbol::HandChoose)])],
            ['kind' => 'P', 'src' => 4, 'spec' => TestSpecs::spec('d', touches: [new ResourceTouch(ResourceSymbol::BudgetGain)])],
        ];

        // a-b（マヤ）、b-c（手札）が連なって 1 つの集合。d はどれとも衝突しない
        $this->assertSame([[1, 2, 3]], $this->sets($game, $rows));
    }

    public function testActiveOnlyEffectsConflictWithSomethingThatChangesActivity(): void
    {
        $game = (new FakeGame())->player(1);
        $a = $game->satellite(1, SatelliteShape::Hammer);
        $game->deploy($a, 'P001')->deploy($a, 'P002')->deploy($a, 'P003');
        $swap = TestSpecs::spec('swap', touches: [new ResourceTouch(ResourceSymbol::Activity, ResourceScope::AllSatellites)]);
        $active = TestSpecs::spec('active', activeOnly: true);
        $any = TestSpecs::spec('any');

        $this->assertSame([[1, 2]], $this->sets($game, [
            ['kind' => 'P', 'src' => 1, 'spec' => $swap],
            ['kind' => 'P', 'src' => 2, 'spec' => $active],
        ]));
        $this->assertSame([], $this->sets($game, [
            ['kind' => 'P', 'src' => 1, 'spec' => $swap],
            ['kind' => 'P', 'src' => 3, 'spec' => $any],
        ]));
        // ACTIVE_ONLY の効果同士は衝突しない
        $this->assertSame([], $this->sets($game, [
            ['kind' => 'P', 'src' => 2, 'spec' => $active],
            ['kind' => 'P', 'src' => 3, 'spec' => $active],
        ]));
    }

    public function testResourceTouchScopeOverlap(): void
    {
        $all = new ResourceTouch(ResourceSymbol::FuelRecover, ResourceScope::AllSatellites);
        $sat1 = new ResourceTouch(ResourceSymbol::FuelConsume, ResourceScope::Satellite, 1);
        $sat2 = new ResourceTouch(ResourceSymbol::FuelConsume, ResourceScope::Satellite, 2);
        $player = new ResourceTouch(ResourceSymbol::FuelConsume);

        $this->assertTrue($all->overlaps($sat1));
        $this->assertFalse($sat1->overlaps($sat2));
        $this->assertFalse($all->overlaps($player));
        $this->assertTrue($all->conflictsWith($sat1));
        $this->assertFalse($sat1->conflictsWith($sat2));
        $this->assertSame(
            ResourceScope::AllSatellites,
            (new ResourceTouch(ResourceSymbol::FuelConsume, ResourceScope::HostSatellite))->bind(null, 5)->scope,
        );
        $this->assertSame(5, (new ResourceTouch(ResourceSymbol::FuelConsume, ResourceScope::EventSatellite))->bind(1, 5)->satelliteId);
    }
}
