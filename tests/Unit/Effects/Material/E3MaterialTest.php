<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects\Material;

use Bga\Games\projectedgeworth\Effects\Parts\PartRegistry;
use Bga\Games\projectedgeworth\Effects\Parts\PartsE3;
use Bga\Games\projectedgeworth\Material\CardEffects;
use Bga\Games\projectedgeworth\Material\CardEffectsE3;
use Bga\Games\projectedgeworth\Material\CostKind;
use Bga\Games\projectedgeworth\Material\EffectKind;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\PartId;
use Bga\Games\projectedgeworth\Material\ProjectCards;
use Bga\Games\projectedgeworth\Material\SatelliteCards;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\Effects\E3Cards;

/**
 * E-3（任意の効果と問い）22 種の定義が、002 のカードデータ・007 §10 の付表・011 §7 の表と一致すること。
 */
final class E3MaterialTest extends TestCase
{
    /**
     * @return array<string, array{string, string, Hook, PartId, bool, string, list<string>}>
     *     カード => [ID または name_id, 窓の記号, フック, 部品, 任意か, 支払い（なしは ''）, 007 §10 の資源]
     */
    public static function cards(): array
    {
        return [
            'P005' => ['P005', 'B3', Hook::BudgetFree, PartId::DrawCards, true, 'PAY_MAYA', ['M-', 'H+']],
            'P006' => ['P006', 'B0', Hook::BudgetStart, PartId::ChangeBudget, true, 'PAY_MAYA', ['M-', 'B+']],
            'P019' => ['P019', 'DC', Hook::DeployCost, PartId::DeployCostMod, true, '', []],
            'P020' => ['P020', 'DC', Hook::DeployCost, PartId::DeployCostMod, true, '', []],
            'P021' => ['P021', 'DC', Hook::DeployCost, PartId::DeployCostMod, true, '', []],
            'P036' => ['P036', 'LC', Hook::LaunchCost, PartId::LaunchCostMod, true, '', []],
            'P068' => ['P068', 'PA', Hook::ProposalOptions, PartId::ProposalDiscardScope, false, '', []],
            'P069' => ['P069', 'PB', Hook::ProposalAfter, PartId::ChangeBudget, true, 'PAY_MAYA', ['M-', 'B+']],
            'P070' => ['P070', 'PA', Hook::ProposalOptions, PartId::GainMaya, true, 'DISCARD_COST', ['H*', 'M+']],
            'P072' => ['P072', 'PA', Hook::ProposalOptions, PartId::ProposalKeepPurchase, true, 'PAY_MAYA_TIER', ['M-']],
            'P086' => ['P086', 'O1', Hook::OrbitEntered, PartId::DrawCards, true, 'PAY_MAYA', ['M-', 'H+']],
            'P087' => ['P087', 'O0', Hook::OrbitStart, PartId::DiscardHand, false, '', ['H*']],
            'R016' => ['hammer_europa', 'XD', Hook::BonusDeclare, PartId::GrantActionBonus, true, 'PAY_MAYA', ['M-']],
            'R017' => ['retro_europa', 'XD', Hook::BonusDeclare, PartId::GrantActionBonus, true, 'PAY_MAYA', ['M-']],
            'R018' => ['scallop_europa', 'XD', Hook::BonusDeclare, PartId::GrantActionBonus, true, 'PAY_MAYA', ['M-']],
            'R019' => ['concorde_europa', 'XD', Hook::BonusDeclare, PartId::GrantActionBonus, true, 'PAY_MAYA', ['M-']],
            'R020' => ['stealth_europa', 'XD', Hook::BonusDeclare, PartId::GrantActionBonus, true, 'PAY_MAYA', ['M-']],
            'R021' => ['hammer_ganymede', 'KC', Hook::LinkCost, PartId::LinkCostMod, true, '', []],
            'R022' => ['retro_ganymede', 'KC', Hook::LinkCost, PartId::LinkCostMod, true, '', []],
            'R023' => ['scallop_ganymede', 'KC', Hook::LinkCost, PartId::LinkCostMod, true, '', []],
            'R024' => ['concorde_ganymede', 'KC', Hook::LinkCost, PartId::LinkCostMod, true, '', []],
            'R025' => ['stealth_ganymede', 'KC', Hook::LinkCost, PartId::LinkCostMod, true, '', []],
        ];
    }

    #[DataProvider('cards')]
    public function testDefinitionMatchesTheTables(string $id, string $window, Hook $hook, PartId $part, bool $optional, string $cost, array $touches): void
    {
        $spec = preg_match('/^P\d{3}$/', $id) === 1 ? CardEffects::forProject($id) : CardEffects::get($id);
        $this->assertNotNull($spec, $id);
        $this->assertSame($hook, $spec->hook);
        $this->assertSame($window, $spec->hook->windowSymbol());
        $this->assertSame($part, $spec->part);
        $this->assertSame($optional, $spec->optional);
        $this->assertSame($cost, $spec->cost?->kind->value ?? '');
        $this->assertSame($touches, array_map(static fn($t): string => $t->symbol->value, $spec->touches));
    }

    #[DataProvider('cards')]
    public function testKindAndActiveConditionAgreeWith002(string $id, mixed ...$rest): void
    {
        $spec = preg_match('/^P\d{3}$/', $id) === 1 ? CardEffects::forProject($id) : CardEffects::get($id);
        $this->assertNotNull($spec);
        if (preg_match('/^P\d{3}$/', $id) === 1) {
            $card = ProjectCards::get($id);
            $this->assertSame($card->effectKind->value, $spec->kind->value, $id);
            $this->assertSame($card->activeCondition->value === 'ACTIVE_ONLY', $spec->activeOnly, $id);
        } else {
            $this->assertSame(EffectKind::Activated, $spec->kind);
            $this->assertTrue($spec->activeOnly, $id);
        }
    }

    public function testThereAre22Cards(): void
    {
        $keys = E3Cards::keys();
        $this->assertCount(22, $keys);
        $this->assertCount(22, array_unique($keys));
        // P006 は E-0 の代表として CardEffects が先に持つので、CardEffectsE3 は残りの 21 種
        $this->assertCount(21, CardEffectsE3::specs());
        foreach ($keys as $key) {
            $this->assertNotNull(CardEffects::get($key), $key);
        }
    }

    public function testStageAndTierDetails(): void
    {
        $this->assertSame(2, CardEffects::forProject('P087')?->stages);
        $this->assertSame(CostKind::PayMayaTier, CardEffects::forProject('P072')?->cost?->kind);
        $this->assertSame([4 => 1, 7 => 2, 10 => 3], CardEffects::forProject('P072')?->cost?->tiers);
        $this->assertSame(['hammer_europa' => 'PROPOSAL', 'retro_europa' => 'DEPLOY', 'scallop_europa' => 'LINK', 'concorde_europa' => 'LAUNCH_TURN', 'stealth_europa' => 'BUDGET'], array_combine(
            ['hammer_europa', 'retro_europa', 'scallop_europa', 'concorde_europa', 'stealth_europa'],
            array_map(static fn(string $k): string => (string) CardEffects::get($k)?->params['action'], ['hammer_europa', 'retro_europa', 'scallop_europa', 'concorde_europa', 'stealth_europa']),
        ));
    }

    public function testPartsRegistration(): void
    {
        $registry = PartsE3::register(new PartRegistry());
        foreach ([PartId::DiscardHand, PartId::GrantActionBonus, PartId::ProposalDiscardScope, PartId::ProposalKeepPurchase] as $id) {
            $this->assertTrue($registry->has($id), $id->value);
        }
    }

    public function testEveryE3SatelliteKeyIsARealSatelliteCard(): void
    {
        $names = array_map(static fn($c): string => $c->nameId, SatelliteCards::all());
        foreach (E3Cards::SATELLITE_KEYS as $key) {
            $this->assertContains($key, $names);
        }
    }
}
