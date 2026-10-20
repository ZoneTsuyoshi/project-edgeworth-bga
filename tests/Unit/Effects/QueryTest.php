<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects;

use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Data\Satellite;
use Bga\Games\projectedgeworth\Effects\Condition\ConditionEvaluator;
use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Parts\ModifierPart;
use Bga\Games\projectedgeworth\Effects\Parts\PartRegistry;
use Bga\Games\projectedgeworth\Effects\Query\CostBreakdown;
use Bga\Games\projectedgeworth\Effects\Query\EffectQueries;
use Bga\Games\projectedgeworth\Effects\Query\EngineActionHooks;
use Bga\Games\projectedgeworth\Effects\Query\EngineOrbitModifiers;
use Bga\Games\projectedgeworth\Effects\Query\ModifierQuery;
use Bga\Games\projectedgeworth\Effects\Query\NearMissAdjust;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\projectedgeworth\Flow\Action\ProposalCounts;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Flow\OrbitSatellite;
use Bga\Games\projectedgeworth\Material\ConditionId;
use Bga\Games\projectedgeworth\Material\EffectCatalog;
use Bga\Games\projectedgeworth\Material\EffectCondition;
use Bga\Games\projectedgeworth\Material\EffectKind;
use Bga\Games\projectedgeworth\Material\EffectSpec;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\PartId;
use Bga\Games\projectedgeworth\Material\ProjectType;
use Bga\Games\projectedgeworth\Material\SatelliteColor;
use Bga\Games\projectedgeworth\Material\SatellitePlan;
use Bga\Games\projectedgeworth\Material\SatelliteShape;
use ProjectEdgeworthTests\Support\Effects\ArrayCatalog;
use ProjectEdgeworthTests\Support\Effects\FakeGame;
use ProjectEdgeworthTests\Support\Effects\SampleParts;
use ProjectEdgeworthTests\Support\Effects\TestSpecs;

/**
 * 修正の問い合わせ（011 §3-2）の骨組み、コストの内訳（下限 0）、既存のルール層のフック（ActionHooks・OrbitModifiers）への接続。
 */
final class QueryTest extends EffectsTestCase
{
    private function queries(FakeGame $game, EffectCatalog $catalog, ?PartRegistry $parts = null): EffectQueries
    {
        return new EffectQueries(new ModifierQuery(
            $game,
            $catalog,
            $parts ?? SampleParts::registry($game),
            new ConditionEvaluator(),
        ));
    }

    private function satelliteRow(FakeGame $game, int $id, SatelliteShape $shape = SatelliteShape::Hammer, bool $active = true): Satellite
    {
        return new Satellite(
            $id,
            1,
            SatelliteColor::Red,
            $shape,
            SatellitePlan::Moon,
            'R001R',
            $active ? 5 : null,
            $active ? Direction::NE : null,
            0,
            false,
            0,
        );
    }

    // ---- コストの内訳 ----

    public function testCostBreakdownFloorsAtZeroOnceAfterAllModifiersThenAddsTheSurcharge(): void
    {
        $cost = (new CostBreakdown(5))->withModifier('a', -3)->withModifier('b', -4);

        $this->assertSame(0, $cost->total(), '7 引いても 0 未満にならない');
        $this->assertSame(7, $cost->reduction());
        // 特殊通信確立の +5 は下限の後（007 §5-4 の手順 6）
        $this->assertSame(5, $cost->withAfterFloor('special', 5)->total());
        $this->assertSame(2, (new CostBreakdown(5))->withModifier('a', -3)->total());
    }

    // ---- 修正の問い合わせ ----

    public function testConstantModifiersApplyToTheirScopeAndStack(): void
    {
        $game = (new FakeGame())->player(1);
        $a = $game->satellite(1, SatelliteShape::Hammer);
        $b = $game->satellite(1, SatelliteShape::Retro);
        $game->deploy($a, 'P010');
        $catalog = new ArrayCatalog([
            ...array_values(\Bga\Games\projectedgeworth\Material\CardEffects::all()),
            TestSpecs::spec('pseudo_all', Hook::DeployCost, PartId::DeployCostMod, params: ['delta' => -1]),
        ]);
        $game->satellite(1, SatelliteShape::Scallop, true, 'pseudo_all');
        $queries = $this->queries($game, $catalog);

        // P010 は自分の衛星 a だけ -2、全衛星の -1 と加算（5 - 2 - 1 = 2、b は 5 - 1 = 4）
        $this->assertSame(2, $queries->deployCost(new QueryContext(1, $a), 5)->total());
        $this->assertSame(4, $queries->deployCost(new QueryContext(1, $b), 5)->total());
    }

    public function testActivatedModifiersApplyOnlyWhenChosenAndInactiveActiveOnlyOnesDoNot(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Hammer, true, 'fleet');
        $catalog = new ArrayCatalog([TestSpecs::spec(
            'fleet',
            Hook::DeployCost,
            PartId::DeployCostMod,
            EffectKind::Activated,
            optional: true,
            activeOnly: true,
            params: ['delta' => -2],
        )]);
        $queries = $this->queries($game, $catalog);
        $query = new QueryContext(1, $sat);

        $this->assertSame(5, $queries->deployCost($query, 5)->total(), '[Use] しなければ働かない');
        $this->assertSame(3, $queries->deployCost($query, 5, ['S:' . $sat])->total());

        $game->setActive($sat, false);
        $this->assertSame(5, $queries->deployCost($query, 5, ['S:' . $sat])->total(), '非稼働の衛星の ACTIVE_ONLY は働かない');
    }

    public function testConditionsGateModifiers(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Hammer, true, 'crew_discount');
        $catalog = new ArrayCatalog([TestSpecs::spec(
            'crew_discount',
            Hook::DeployCost,
            PartId::DeployCostMod,
            params: ['delta' => -3],
            conditions: [new EffectCondition(ConditionId::EventCardType, ['type' => 'CREW'])],
        )]);
        $queries = $this->queries($game, $catalog);

        $this->assertSame(2, $queries->deployCost(new QueryContext(1, $sat, 1, ProjectType::Crew), 5)->total());
        $this->assertSame(5, $queries->deployCost(new QueryContext(1, $sat, 40, ProjectType::Device), 5)->total());
    }

    public function testSelfCardModifiersCountOnlyForTheCardBeingDeployed(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Hammer);
        $game->deploy($sat, 'P026');
        $catalog = new ArrayCatalog([TestSpecs::spec(
            'french_bulldog',
            Hook::DeployCost,
            PartId::DeployCostMod,
            params: ['delta' => -2, 'scope' => 'SELF_CARD'],
        )]);
        $parts = (new PartRegistry())->register(PartId::DeployCostMod, new class implements ModifierPart {
            public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
            {
                return true;
            }

            public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
            {
                return $value instanceof CostBreakdown ? $value->withModifier($spec->cardKey, (int) $spec->params['delta']) : $value;
            }
        });
        $queries = $this->queries($game, $catalog, $parts);
        $query = new QueryContext(1, $sat, 26);
        $self = new EffectHost('P', 26, 'french_bulldog', 1, $sat, SatelliteShape::Hammer, 0, true, false);

        // すでに衛星にある P026 は他のカードの配備コストを変えない。配備しようとする P026 自身にだけ効く
        $this->assertSame(8, $queries->deployCost($query, 8)->total());
        $this->assertSame(6, $queries->deployCost($query, 8, [], $self)->total());
    }

    public function testForbidEffectsAreCheckedPerAction(): void
    {
        $game = (new FakeGame())->player(1);
        $a = $game->satellite(1, SatelliteShape::Hammer, true, 'no_deploy');
        $b = $game->satellite(1, SatelliteShape::Retro);
        $catalog = new ArrayCatalog([TestSpecs::spec('no_deploy', Hook::Allowed, PartId::Forbid, params: ['action' => 'DEPLOY_TO_THIS'])]);
        $queries = $this->queries($game, $catalog);

        $this->assertFalse($queries->allowed(new QueryContext(1, $a, action: ForbiddenAction::DeployTo)));
        $this->assertTrue($queries->allowed(new QueryContext(1, $a, action: ForbiddenAction::Turn)));
        $this->assertTrue($queries->allowed(new QueryContext(1, $b, action: ForbiddenAction::DeployTo)));
    }

    public function testAnUnimplementedPartFailsLoudly(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Hammer, true, 'fleet');
        $catalog = new ArrayCatalog([TestSpecs::spec('fleet', Hook::DeployCost, PartId::DeployCostMod)]);
        $queries = $this->queries($game, $catalog, new PartRegistry());

        $this->expectException(\LogicException::class);
        $queries->deployCost(new QueryContext(1, $sat), 5);
    }

    public function testResultHooksCannotBeQueried(): void
    {
        $game = (new FakeGame())->player(1);
        $query = new ModifierQuery($game, new ArrayCatalog(), SampleParts::registry($game), new ConditionEvaluator());

        $this->expectException(\InvalidArgumentException::class);
        $query->fold(Hook::Deployed, new QueryContext(1), 0);
    }

    // ---- ActionHooks・OrbitModifiers への接続 ----

    public function testEngineActionHooksReplaceNoActionHooks(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Hammer);
        $other = $game->satellite(1, SatelliteShape::Retro);
        $game->deploy($sat, 'P010');
        $hooks = new EngineActionHooks($this->queries($game, new \Bga\Games\projectedgeworth\Material\CardEffectCatalog()));
        $target = $this->satelliteRow($game, $sat);
        $otherRow = $this->satelliteRow($game, $other, SatelliteShape::Retro);

        // P010: この衛星への配備コスト -2
        $this->assertSame(2, $hooks->deployCostReduction(1, $target, 40, 1));
        $this->assertSame(0, $hooks->deployCostReduction(1, $otherRow, 40, 0));
        // 効果のない問い合わせは「修正なし」のまま
        $this->assertSame(0, $hooks->launchCostReduction(1, $target));
        $this->assertSame([], $hooks->extraLinkTargets(1, $target, StepMode::Main));
        $this->assertFalse($hooks->pioneerWaived(1, $target));
        $this->assertSame(0, $hooks->linkCostReduction(1, $target, 7));
        foreach (ForbiddenAction::cases() as $action) {
            $this->assertFalse($hooks->isForbidden($action, $target));
        }
        $counts = new ProposalCounts(5, 2);
        $this->assertEquals($counts, $hooks->proposalCounts(1, true, $counts));
        $this->assertSame(17, $hooks->budgetMainGain(1, 17));
        $this->assertSame(3, $hooks->sellPrice(1, 3));
    }

    public function testEngineActionHooksPassTheChosenActivatedModifiers(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Hammer, true, 'fleet');
        $catalog = new ArrayCatalog([TestSpecs::spec(
            'fleet',
            Hook::DeployCost,
            PartId::DeployCostMod,
            EffectKind::Activated,
            optional: true,
            params: ['delta' => -2],
        )]);
        $hooks = new EngineActionHooks($this->queries($game, $catalog));
        $target = $this->satelliteRow($game, $sat);

        $this->assertSame(0, $hooks->deployCostReduction(1, $target, 40, 0));
        $hooks->useMods(['S:' . $sat]);
        $this->assertSame(2, $hooks->deployCostReduction(1, $target, 40, 0));
        $hooks->useMods([]);
        $this->assertSame(0, $hooks->deployCostReduction(1, $target, 40, 0));
    }

    public function testEngineActionHooksAskTheForbidAndOtherQueries(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Hammer, true, 'no_turn');
        $catalog = new ArrayCatalog([TestSpecs::spec('no_turn', Hook::Allowed, PartId::Forbid, params: ['action' => 'TURN_THIS'])]);
        $hooks = new EngineActionHooks($this->queries($game, $catalog));
        $row = $this->satelliteRow($game, $sat);

        $this->assertTrue($hooks->isForbidden(ForbiddenAction::Turn, $row));
        $this->assertFalse($hooks->isForbidden(ForbiddenAction::Link, $row));
    }

    public function testEngineOrbitModifiersAnswerFuelAndNearMissQueries(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Hammer, true, 'zero_g');
        $catalog = new ArrayCatalog([
            TestSpecs::spec('zero_g', Hook::FuelConsume, PartId::FuelConsumeMod),
            TestSpecs::spec('kessler', Hook::NearMiss, PartId::NearMissFuelMod),
            TestSpecs::spec('decon', Hook::NearMiss, PartId::NearMissSuppress),
        ]);
        $game->satellite(1, SatelliteShape::Retro, true, 'kessler');
        $game->satellite(1, SatelliteShape::Scallop, true, 'decon');
        $parts = (new PartRegistry())
            ->register(PartId::FuelConsumeMod, $this->valueModifier(static fn(mixed $v): mixed => 0))
            ->register(PartId::NearMissFuelMod, $this->valueModifier(static fn(mixed $v): mixed => $v instanceof NearMissAdjust ? $v->withFuel(1) : $v))
            ->register(PartId::NearMissSuppress, $this->valueModifier(static fn(mixed $v): mixed => $v instanceof NearMissAdjust ? $v->withExempt() : $v));
        $modifiers = new EngineOrbitModifiers($this->queries($game, $catalog, $parts));
        $orbit = new OrbitSatellite($sat, 1, 5, Direction::NE, 0, 3);

        $this->assertSame(0, $modifiers->moveFuelCost($orbit));
        $this->assertSame(1, $modifiers->nearMissFuelCost($orbit));
        $this->assertTrue($modifiers->nearMissExempt($orbit));
        // O1 の窓（追加移動・突入時の燃料回復）は同期の問い合わせでは表せないので、窓を開く側が扱うまでは 0
        $this->assertSame(0, $modifiers->extraMoves($orbit));
        $this->assertSame(0, $modifiers->entryFuelDelta($orbit));
    }

    public function testEngineOrbitModifiersWithoutEffectsKeepTheBaseRules(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Hammer);
        $modifiers = new EngineOrbitModifiers($this->queries($game, new ArrayCatalog()));
        $orbit = new OrbitSatellite($sat, 1, 5, Direction::NE, 0, 3);

        $this->assertSame(1, $modifiers->moveFuelCost($orbit));
        $this->assertSame(2, $modifiers->nearMissFuelCost($orbit));
        $this->assertFalse($modifiers->nearMissExempt($orbit));
    }

    private function valueModifier(\Closure $fn): ModifierPart
    {
        return new class ($fn) implements ModifierPart {
            public function __construct(private readonly \Closure $fn)
            {
            }

            public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
            {
                return true;
            }

            public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
            {
                return ($this->fn)($value);
            }
        };
    }
}
