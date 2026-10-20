<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects\Material;

use Bga\Games\projectedgeworth\Material\CardEffects;
use Bga\Games\projectedgeworth\Material\ConditionId;
use Bga\Games\projectedgeworth\Material\CostKind;
use Bga\Games\projectedgeworth\Material\EffectKind;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\HookKind;
use Bga\Games\projectedgeworth\Material\PartCategory;
use Bga\Games\projectedgeworth\Material\PartId;
use Bga\Games\projectedgeworth\Material\ProjectCards;
use Bga\Games\projectedgeworth\Material\ResourceSymbol;
use Bga\Games\projectedgeworth\Material\SatelliteCards;
use Bga\Games\projectedgeworth\Material\SatellitePlan;
use Bga\Games\projectedgeworth\Material\SatelliteShape;
use Bga\Games\projectedgeworth\Material\ActiveCondition;
use Bga\Games\projectedgeworth\Material\EffectTiming;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 011 §2〜§4 の列挙の件数と、代表 10 枚の `EffectSpec` が 002・007 §10 と一致すること。
 */
final class EffectMaterialTest extends TestCase
{
    public function testEnumerationsMatchTheSpecification(): void
    {
        $this->assertCount(28, Hook::cases());
        $byKind = array_count_values(array_map(static fn(Hook $h): string => $h->kind()->value, Hook::cases()));
        $this->assertSame([HookKind::Window->value => 14, HookKind::Step->value => 3, HookKind::Query->value => 11], $byKind);

        $this->assertCount(29, PartId::cases());
        $byCategory = array_count_values(array_map(static fn(PartId $p): string => $p->category()->value, PartId::cases()));
        $this->assertSame([PartCategory::Result->value => 15, PartCategory::Modifier->value => 13, PartCategory::Forbid->value => 1], $byCategory);

        $this->assertCount(13, ConditionId::cases());
        $this->assertCount(3, CostKind::cases());
        $this->assertCount(13, ResourceSymbol::cases());
    }

    public function testWindowSymbolsFollow007(): void
    {
        $this->assertSame('PL1', Hook::PlanningRetrieve->windowSymbol());
        $this->assertSame('B0', Hook::BudgetStart->windowSymbol());
        $this->assertSame('DE', Hook::Deployed->windowSymbol());
        $this->assertSame('O4', Hook::Landed->windowSymbol());
        $this->assertSame('DC', Hook::DeployCost->windowSymbol());
        $this->assertSame('KC', Hook::LinkCost->windowSymbol());
        $this->assertNull(Hook::Allowed->windowSymbol());
        $this->assertNull(Hook::ActionBonusStep->windowSymbol());
    }

    /**
     * @return array<string, array{string, string, string, list<string>}> カード ID => [フック, 効果タイプ, 稼働条件, 007 §10 の資源]
     */
    public static function samples(): array
    {
        return [
            'P006' => ['P006', 'BUDGET_START', 'ACTIVATED', ['M-', 'B+']],
            'P010' => ['P010', 'DEPLOY_COST', 'CONSTANT', []],
            'P011' => ['P011', 'DEPLOYED', 'ACTIVATED', ['M-', 'H*', 'C']],
            'P013' => ['P013', 'DEPLOYED', 'ACTIVATED', ['B+']],
            'P023' => ['P023', 'DEPLOYED', 'ACTIVATED', ['H+']],
            'P034' => ['P034', 'LAUNCHED', 'ON_LAUNCH', ['M-', 'F-', 'P', 'Cr']],
            'P035' => ['P035', 'LAUNCHED', 'ON_LAUNCH', ['C', 'Cr']],
            'P038' => ['P038', 'LAUNCHED', 'ACTIVATED', ['F-', 'F+', 'Cr']],
            'P039' => ['P039', 'LAUNCHED', 'ON_LAUNCH', ['H+', 'Cr']],
            // E-2（強制の反応効果）。007 §10 の付表の「資源」列
            'P001' => ['P001', 'PLANNING_RETRIEVE', 'ACTIVATED', []],
            'P002' => ['P002', 'PLANNING_RETRIEVE', 'ACTIVATED', []],
            'P003' => ['P003', 'PLANNING_REVEAL', 'ACTIVATED', ['M+']],
            'P007' => ['P007', 'BUDGET_BONUS_GAIN', 'ACTIVATED', ['M+']],
            'P009' => ['P009', 'DEPLOYED', 'ON_DEPLOY', ['H+']],
            'P025' => ['P025', 'DEPLOYED', 'ACTIVATED', ['H+']],
            'P027' => ['P027', 'DEPLOYED', 'ACTIVATED', ['H+']],
            'P028' => ['P028', 'DEPLOYED', 'ACTIVATED', ['H+']],
            'P029' => ['P029', 'DEPLOYED', 'ACTIVATED', ['H+']],
            'P030' => ['P030', 'DEPLOYED', 'ACTIVATED', ['B+']],
            'P031' => ['P031', 'DEPLOYED', 'ACTIVATED', ['B+']],
            'P032' => ['P032', 'DEPLOYED', 'ACTIVATED', ['B+']],
            'P037' => ['P037', 'TURNED', 'ACTIVATED', ['H+']],
            'P041' => ['P041', 'LAUNCHED', 'ACTIVATED', ['B+']],
            'P042' => ['P042', 'LAUNCHED', 'ON_LAUNCH', ['B+', 'Cr']],
            'P043' => ['P043', 'TURNED', 'ACTIVATED', ['F+']],
            'P049' => ['P049', 'LINKED', 'CONSTANT', ['F+']],
            'P075' => ['P075', 'PROPOSAL_DRAWN', 'CONSTANT', ['M+']],
            'P078' => ['P078', 'LANDED', 'ACTIVATED', ['H+']],
            'P083' => ['P083', 'ORBIT_ENTERED', 'ACTIVATED', ['F+']],
            'P084' => ['P084', 'ORBIT_ENTERED', 'ACTIVATED', ['F+']],
            'P088' => ['P088', 'LANDED', 'ACTIVATED', ['M+']],
            'P089' => ['P089', 'LANDED', 'ACTIVATED', ['M+']],
            'P092' => ['P092', 'LANDED', 'ACTIVATED', ['M+']],
            'P093' => ['P093', 'ORBIT_ENTERED', 'ACTIVATED', ['M+']],
        ];
    }

    /**
     * @param list<string> $touches
     */
    #[DataProvider('samples')]
    public function testSamplesMatchTheCardDataAndTheEffectTable(string $cardId, string $hook, string $kind, array $touches): void
    {
        $definition = ProjectCards::get($cardId);
        $spec = CardEffects::forProject($cardId);

        $this->assertNotNull($spec, $cardId);
        $this->assertSame($definition->nameId, $spec->cardKey);
        $this->assertSame($hook, $spec->hook->value);
        $this->assertSame($kind, $spec->kind->value);
        $this->assertSame($definition->effectKind, $spec->kind);
        $this->assertSame($definition->activeCondition === ActiveCondition::ActiveOnly, $spec->activeOnly);
        $this->assertSame(
            $touches,
            array_map(static fn($t): string => $t->symbol->value, $spec->touches),
            "$cardId の資源が 007 §10 の付表と一致する",
        );
        $this->assertSame($spec->kind === EffectKind::OnLaunch || $spec->kind === EffectKind::OnDeploy, $spec->selfOnly);
    }

    public function testSampleHooksAgreeWithTheCardTimingInTheData(): void
    {
        $timingToHooks = [
            EffectTiming::BudgetPhase->value => [Hook::BudgetStart],
            EffectTiming::Deployed->value => [Hook::Deployed, Hook::DeployCost],
            EffectTiming::Launched->value => [Hook::Launched, Hook::LaunchCost],
        ];
        foreach (['P006', 'P010', 'P011', 'P013', 'P023', 'P034', 'P035', 'P038', 'P039'] as $cardId) {
            $spec = CardEffects::forProject($cardId);
            $timing = ProjectCards::get($cardId)->timing->value;
            $this->assertContains($spec?->hook, $timingToHooks[$timing], $cardId);
        }
    }

    public function testLookupByProjectIdAndSatelliteFace(): void
    {
        $this->assertSame('budget_negotiator', CardEffects::forProject('P006')?->cardKey);
        $stealthPhobos = CardEffects::forSatelliteFace(SatelliteShape::Stealth, SatellitePlan::Phobos);
        $this->assertSame('stealth_phobos', $stealthPhobos?->cardKey);
        $this->assertSame(PartId::GainMaya, $stealthPhobos->part);
        $this->assertSame(['M+'], array_map(static fn($t): string => $t->symbol->value, $stealthPhobos->touches));
        $this->assertSame('flyby_link', CardEffects::forProject('P099')?->cardKey);
        $this->assertNull(CardEffects::get('no_such_card'));
    }

    public function testEveryDefinedEffectBelongsToARealCard(): void
    {
        $names = array_map(static fn($c): string => $c->nameId, ProjectCards::all());
        $names = [...$names, ...array_map(static fn($c): string => $c->nameId, SatelliteCards::all())];
        foreach (CardEffects::all() as $key => $spec) {
            $this->assertSame($key, $spec->cardKey);
            $this->assertContains($key, $names);
        }
    }
}
