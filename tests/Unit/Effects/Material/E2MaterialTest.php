<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects\Material;

use Bga\Games\ProjectEdgeworth\Effects\Parts\PartRegistry;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartsE2;
use Bga\Games\ProjectEdgeworth\Material\ActiveCondition;
use Bga\Games\ProjectEdgeworth\Material\CardEffects;
use Bga\Games\ProjectEdgeworth\Material\CardEffectsE2;
use Bga\Games\ProjectEdgeworth\Material\EffectKind;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;
use Bga\Games\ProjectEdgeworth\Material\Hook;
use Bga\Games\ProjectEdgeworth\Material\HookKind;
use Bga\Games\ProjectEdgeworth\Material\PartCategory;
use Bga\Games\ProjectEdgeworth\Material\PartId;
use Bga\Games\ProjectEdgeworth\Material\ProjectCards;
use Bga\Games\ProjectEdgeworth\Material\SatelliteCards;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\Effects\E2Cards;

/**
 * E-2（強制の反応効果）36 種の定義が、002 のカードデータ・007 §10 の付表・011 §7 の表と一致すること。
 */
final class E2MaterialTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, PartId, array<string, mixed>, list<string>}>
     *     カード => [ID または name_id, 007 の窓の記号（なしは ''）, 011 §7 のフック, 部品, 部品のパラメータ, 007 §10 の資源（衛星カード）]
     */
    public static function satelliteCards(): array
    {
        return [
            'R001' => ['hammer_moon', 'LE', 'LAUNCHED', PartId::DrawCards, ['count' => 1], ['H+']],
            'R002' => ['retro_moon', 'LE', 'LAUNCHED', PartId::ChangeBudget, ['amount' => 2], ['B+']],
            'R003' => ['scallop_moon', 'LE', 'LAUNCHED', PartId::DrawCards, ['count' => 2], ['H+']],
            'R004' => ['concorde_moon', 'LE', 'LAUNCHED', PartId::ChangeBudget, ['amount' => 4], ['B+']],
            'R010' => ['stealth_phobos', 'B0', 'BUDGET_START', PartId::GainMaya, ['amount' => 2], ['M+']],
            'R012' => ['retro_deimos', '', 'ACTION_BONUS_STEP', PartId::ChangeBudget, ['amount' => 1, 'action' => 'LAUNCH_TURN'], ['B+']],
            'R013' => ['scallop_deimos', '', 'ACTION_BONUS_STEP', PartId::DrawCards, ['count' => 1, 'action' => 'DEPLOY'], ['H+']],
            'R015' => ['stealth_deimos', 'B2', 'BUDGET_BONUS_GAIN', PartId::GainMaya, ['amount' => 3], ['M+']],
        ];
    }

    /**
     * @return array<string, array{string, string, PartId, array<string, mixed>}> カード ID => [007 の窓の記号, 011 §7 の部品…]
     */
    public static function projectCards(): array
    {
        return [
            'P001' => ['P001', 'PL1', PartId::KeepPlottedActions, ['actions' => [2, 3]]],
            'P002' => ['P002', 'PL1', PartId::KeepPlottedActions, ['actions' => [4, 5]]],
            'P003' => ['P003', 'PL2', PartId::GainMaya, ['amount' => 2]],
            'P007' => ['P007', 'B2', PartId::GainMaya, ['amount' => 2]],
            'P009' => ['P009', 'DE', PartId::DrawCards, ['count' => 1]],
            'P013' => ['P013', 'DE', PartId::ChangeBudget, ['amount' => 1]],
            'P023' => ['P023', 'DE', PartId::DrawCards, ['count' => 1]],
            'P025' => ['P025', 'DE', PartId::DrawCards, ['count' => 2]],
            'P027' => ['P027', 'DE', PartId::DrawCards, ['count' => 1]],
            'P028' => ['P028', 'DE', PartId::DrawCards, ['count' => 1]],
            'P029' => ['P029', 'DE', PartId::DrawCards, ['count' => 1]],
            'P030' => ['P030', 'DE', PartId::ChangeBudget, ['amount' => 1]],
            'P031' => ['P031', 'DE', PartId::ChangeBudget, ['amount' => 1]],
            'P032' => ['P032', 'DE', PartId::ChangeBudget, ['amount' => 1]],
            'P037' => ['P037', 'TE', PartId::DrawCards, ['count' => 1]],
            'P039' => ['P039', 'LE', PartId::DrawCards, ['countDevice' => true]],
            'P041' => ['P041', 'LE', PartId::ChangeBudget, ['amount' => 1]],
            'P042' => ['P042', 'LE', PartId::ChangeBudget, ['amount' => 1, 'perDevice' => true]],
            'P043' => ['P043', 'TE', PartId::RecoverFuel, ['amount' => 1, 'target' => 'THIS']],
            'P049' => ['P049', 'KE', PartId::RecoverFuel, ['amount' => 1, 'target' => 'THIS']],
            'P075' => ['P075', 'PA', PartId::GainMaya, ['perCardDrawn' => 1]],
            'P078' => ['P078', 'O4', PartId::DrawCards, ['count' => 1]],
            'P083' => ['P083', 'O1', PartId::RecoverFuel, ['amount' => 2, 'target' => 'THIS']],
            'P084' => ['P084', 'O1', PartId::RecoverFuel, ['amount' => 2, 'target' => 'THIS']],
            'P088' => ['P088', 'O4', PartId::GainMaya, ['amount' => 8]],
            'P089' => ['P089', 'O4', PartId::GainMaya, ['amount' => 15]],
            'P092' => ['P092', 'O4', PartId::GainMaya, ['amount' => 10]],
            'P093' => ['P093', 'O1', PartId::GainMaya, ['amount' => 3]],
        ];
    }

    public function testThirtySixEffectsAreDefined(): void
    {
        $keys = E2Cards::keys();
        $this->assertCount(36, $keys);
        $this->assertCount(36, array_unique($keys));
        foreach ($keys as $key) {
            $this->assertNotNull(CardEffects::get($key), $key);
        }
        // E-0 の代表 10 枚のうち E-2 の 4 種（P013・P023・P039・R010）を除いた 32 種が CardEffectsE2 にある
        $this->assertCount(32, CardEffectsE2::build());
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('projectCards')]
    public function testProjectCardDefinitionsMatchTheDataAndTheTables(string $cardId, string $window, PartId $part, array $params): void
    {
        $definition = ProjectCards::get($cardId);
        $spec = CardEffects::forProject($cardId);

        $this->assertNotNull($spec);
        $this->assertSame($window, $spec->hook->windowSymbol(), $cardId);
        $this->assertSame($part, $spec->part, $cardId);
        $this->assertSame($params, $spec->params, $cardId);
        $this->assertSame($definition->effectKind, $spec->kind, $cardId);
        $this->assertSame($definition->activeCondition === ActiveCondition::ActiveOnly, $spec->activeOnly, $cardId);
        $this->assertSelfContained($spec);
    }

    /**
     * @param array<string, mixed> $params
     * @param list<string> $touches
     */
    #[DataProvider('satelliteCards')]
    public function testSatelliteCardDefinitionsMatchTheDataAndTheTables(string $key, string $window, string $hook, PartId $part, array $params, array $touches): void
    {
        $spec = CardEffects::get($key);
        $card = null;
        foreach (SatelliteCards::all() as $candidate) {
            if ($candidate->nameId === $key) {
                $card = $candidate;
                break;
            }
        }

        $this->assertNotNull($spec);
        $this->assertNotNull($card);
        $this->assertSame($hook, $spec->hook->value, $key);
        $this->assertSame($window, (string) $spec->hook->windowSymbol(), $key);
        $this->assertSame($part, $spec->part, $key);
        $this->assertSame($params, $spec->params, $key);
        $this->assertSame($card->effectKind, $spec->kind, $key);
        $this->assertSame($card->activeCondition === ActiveCondition::ActiveOnly, $spec->activeOnly, $key);
        $this->assertSame($touches, array_map(static fn($t): string => $t->symbol->value, $spec->touches), $key);
        $this->assertSelfContained($spec);
    }

    public function testMoonFacesOfEveryColorShareOneDefinition(): void
    {
        foreach (SatelliteCards::all() as $card) {
            if ($card->plan->value === 'MOON') {
                $this->assertSame($card->nameId, CardEffects::forSatelliteFace($card->shape, $card->plan)?->cardKey ?? $card->nameId);
            }
        }
        $moons = array_filter(E2Cards::keys(), static fn(string $k): bool => str_ends_with($k, '_moon') && $k !== 'stealth_moon');
        $this->assertCount(4, $moons);
    }

    public function testHooksAgreeWithTheTimingInTheCardData(): void
    {
        $allowed = [
            'PLANNING_PHASE' => [Hook::PlanningRetrieve, Hook::PlanningReveal],
            'BUDGET_PHASE' => [Hook::BudgetStart, Hook::BudgetBonusGain],
            'DEPLOYED' => [Hook::Deployed, Hook::ActionBonusStep],
            'LAUNCHED' => [Hook::Launched, Hook::ActionBonusStep],
            'TURNED' => [Hook::Turned],
            'LINKED' => [Hook::Linked],
            'PROPOSAL_PHASE' => [Hook::ProposalDrawn],
            'ORBIT_PHASE' => [Hook::OrbitEntered, Hook::Landed],
        ];
        foreach (E2Cards::PROJECT_IDS as $cardId) {
            $spec = CardEffects::forProject($cardId);
            $timing = ProjectCards::get($cardId)->timing->value;
            $this->assertContains($spec?->hook, $allowed[$timing] ?? [], "$cardId ($timing)");
        }
        foreach (SatelliteCards::all() as $card) {
            $spec = CardEffects::get($card->nameId);
            if ($spec !== null && in_array($card->nameId, E2Cards::SATELLITE_KEYS, true)) {
                $this->assertContains($spec->hook, $allowed[$card->timing->value] ?? [], $card->nameId . ' (' . $card->timing->value . ')');
            }
        }
    }

    public function testEveryPartUsedByE2IsRegistered(): void
    {
        $registry = PartsE2::register(new PartRegistry());
        foreach (E2Cards::keys() as $key) {
            $spec = CardEffects::get($key);
            $this->assertTrue($registry->has($spec->part), $key);
        }
    }

    private function assertSelfContained(EffectSpec $spec): void
    {
        $this->assertSame(PartCategory::Result, $spec->part->category(), $spec->cardKey);
        $this->assertNotSame(HookKind::Query, $spec->hook->kind(), $spec->cardKey);
        $this->assertFalse($spec->optional, $spec->cardKey);
        $this->assertNull($spec->cost, $spec->cardKey);
        $this->assertSame(1, $spec->stages, $spec->cardKey);
        $this->assertSame($spec->kind === EffectKind::OnDeploy || $spec->kind === EffectKind::OnLaunch, $spec->selfOnly, $spec->cardKey);
    }
}
