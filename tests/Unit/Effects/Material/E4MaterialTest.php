<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects\Material;

use Bga\Games\ProjectEdgeworth\Board\BoardFace;
use Bga\Games\ProjectEdgeworth\Board\BoardLayoutBuilder;
use Bga\Games\ProjectEdgeworth\Board\BoardPlacement;
use Bga\Games\ProjectEdgeworth\Effects\Condition\ConditionEvaluator;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartRegistry;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartsE1;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartsE4;
use Bga\Games\ProjectEdgeworth\Effects\Query\EffectQueries;
use Bga\Games\ProjectEdgeworth\Effects\Query\ModifierQuery;
use Bga\Games\ProjectEdgeworth\Material\CardEffectCatalog;
use Bga\Games\ProjectEdgeworth\Material\CardEffects;
use Bga\Games\ProjectEdgeworth\Material\CardEffectsE4;
use Bga\Games\ProjectEdgeworth\Material\CostKind;
use Bga\Games\ProjectEdgeworth\Material\EffectKind;
use Bga\Games\ProjectEdgeworth\Material\Hook;
use Bga\Games\ProjectEdgeworth\Material\PartId;
use Bga\Games\ProjectEdgeworth\Material\ProjectCards;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\Effects\E4Cards;
use ProjectEdgeworthTests\Support\Effects\FakeGame;
use ProjectEdgeworthTests\Support\SeededRandom;

/**
 * E-4（衛星の操作）11 種の定義が、002 のカードデータ・007 §10 の付表・011 §7 の表と一致すること。
 */
final class E4MaterialTest extends TestCase
{
    /**
     * @return array<string, array{string, string, Hook, PartId, string, list<string>, list<string>}>
     *     カード => [ID, 窓の記号, フック, 部品, 支払い（なしは ''）, 007 §10 の資源, 条件の ID]
     */
    public static function cards(): array
    {
        return [
            'P004' => ['P004', 'B3', Hook::BudgetFree, PartId::MoveSatellite, 'PAY_MAYA', ['M-', 'F-', 'P'], []],
            'P024' => ['P024', 'DE', Hook::Deployed, PartId::SwapSatellites, '', ['A', 'F-', 'P', 'C'], []],
            'P034' => ['P034', 'LE', Hook::Launched, PartId::MoveSatellite, 'PAY_MAYA_TIER', ['M-', 'F-', 'P', 'Cr'], []],
            'P038' => ['P038', 'LE', Hook::Launched, PartId::TransferFuel, '', ['F-', 'F+', 'Cr'], ['EVENT_SATELLITE']],
            'P044' => ['P044', 'TE', Hook::Turned, PartId::TurnSatellite, '', ['D'], ['EVENT_SATELLITE']],
            'P048' => ['P048', 'KE', Hook::Linked, PartId::TurnSatellite, '', ['D'], ['EVENT_SATELLITE']],
            'P081' => ['P081', 'O1', Hook::OrbitEntered, PartId::MoveSatellite, 'PAY_MAYA', ['M-', 'F-', 'P'], ['EVENT_SATELLITE', 'ENTERED_OWN_LINKED_AREA']],
            'P082' => ['P082', 'O1', Hook::OrbitEntered, PartId::MoveSatellite, '', ['F-', 'P'], ['EVENT_SATELLITE', 'FULL_TYPES']],
            'P090' => ['P090', 'O1', Hook::OrbitEntered, PartId::TurnSatellite, '', ['D'], ['EVENT_SATELLITE', 'REGION_CHANGED']],
            'P091' => ['P091', 'O1', Hook::OrbitEntered, PartId::MoveSatellite, '', ['F-', 'P'], ['EVENT_SATELLITE', 'REGION_CHANGED']],
            'P095' => ['P095', 'O1', Hook::OrbitEntered, PartId::TurnSatellite, '', ['D'], ['EVENT_SATELLITE', 'ENTERED_OWN_LINKED_AREA']],
        ];
    }

    #[DataProvider('cards')]
    public function testDefinitionMatchesTheTables(string $id, string $window, Hook $hook, PartId $part, string $cost, array $touches, array $conditions): void
    {
        $spec = CardEffects::forProject($id);
        $this->assertNotNull($spec, $id);
        $this->assertSame($hook, $spec->hook);
        $this->assertSame($window, $spec->hook->windowSymbol());
        $this->assertSame($part, $spec->part);
        $this->assertSame($cost, $spec->cost?->kind->value ?? '');
        $this->assertSame($touches, array_map(static fn($t): string => $t->symbol->value, $spec->touches));
        $this->assertSame($conditions, array_map(static fn($c): string => $c->id->value, $spec->conditions));
    }

    #[DataProvider('cards')]
    public function testKindActiveConditionAndOptionalAgreeWith002(string $id, mixed ...$rest): void
    {
        $spec = CardEffects::forProject($id);
        $card = ProjectCards::get($id);
        $this->assertNotNull($spec);
        $this->assertSame($card->effectKind->value, $spec->kind->value, $id);
        $this->assertSame($card->activeCondition->value === 'ACTIVE_ONLY', $spec->activeOnly, $id);
        // 11 種はすべて任意の効果（[Use] / [Skip]）
        $this->assertTrue($spec->optional, $id);
    }

    public function testThereAre11CardsAndNineAreInCardEffectsE4(): void
    {
        $keys = E4Cards::keys();
        $this->assertCount(11, $keys);
        $this->assertCount(11, array_unique($keys));
        // P034・P038 は E-0 の代表として CardEffects が先に持つので、CardEffectsE4 は残りの 9 種
        $this->assertCount(9, CardEffectsE4::specs());
        foreach ($keys as $key) {
            $this->assertNotNull(CardEffects::get($key), $key);
        }
    }

    public function testParameters(): void
    {
        $p004 = CardEffects::forProject('P004');
        $this->assertSame(['target' => 'CHOSEN_OWN_ACTIVE', 'areas' => 1, 'fuelPerArea' => 1, 'fireEntered' => false], $p004?->params);
        $this->assertSame(2, $p004?->cost?->amount);
        $this->assertSame([2 => 1, 3 => 2, 4 => 3, 5 => 4], CardEffects::forProject('P034')?->cost?->tiers);
        $this->assertSame(CostKind::PayMayaTier, CardEffects::forProject('P034')?->cost?->kind);
        foreach (['P081', 'P082', 'P091'] as $id) {
            $params = CardEffects::forProject($id)?->params;
            $this->assertSame(['target' => 'THIS', 'areas' => 1, 'fuelPerArea' => 1, 'fireEntered' => true], $params, $id);
        }
        $this->assertSame(2, CardEffects::forProject('P081')?->cost?->amount);
        // P024 は配備時（自身の配備にだけ反応）、他は起動
        $this->assertSame(EffectKind::OnDeploy, CardEffects::forProject('P024')?->kind);
        $this->assertTrue(CardEffects::forProject('P024')?->selfOnly);
        $this->assertSame(['scope' => 'OTHER_OWN'], CardEffects::forProject('P044')?->conditions[0]->params);
    }

    public function testPartsRegistration(): void
    {
        $game = new FakeGame();
        $layout = BoardLayoutBuilder::build(BoardFace::STANDARD, BoardPlacement::EARTH, 4, new SeededRandom(1));
        $parts = PartsE1::register(new PartRegistry(), $game, $layout);
        $queries = new EffectQueries(new ModifierQuery($game, new CardEffectCatalog(), $parts, new ConditionEvaluator()));
        $registry = PartsE4::register($parts, $layout, $queries);
        foreach ([PartId::TransferFuel, PartId::MoveSatellite, PartId::TurnSatellite, PartId::SwapSatellites] as $id) {
            $this->assertTrue($registry->has($id), $id->value);
        }
    }
}
