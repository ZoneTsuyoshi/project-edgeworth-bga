<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects\Material;

use Bga\Games\projectedgeworth\Board\BoardFace;
use Bga\Games\projectedgeworth\Board\BoardLayoutBuilder;
use Bga\Games\projectedgeworth\Board\BoardPlacement;
use Bga\Games\projectedgeworth\Effects\Condition\ConditionEvaluator;
use Bga\Games\projectedgeworth\Effects\Parts\PartRegistry;
use Bga\Games\projectedgeworth\Effects\Parts\PartsE1;
use Bga\Games\projectedgeworth\Effects\Parts\PartsE6;
use Bga\Games\projectedgeworth\Effects\Query\EffectQueries;
use Bga\Games\projectedgeworth\Effects\Query\ModifierQuery;
use Bga\Games\projectedgeworth\Effects\Query\UseMods;
use Bga\Games\projectedgeworth\Material\CardEffectCatalog;
use Bga\Games\projectedgeworth\Material\CardEffects;
use Bga\Games\projectedgeworth\Material\CardEffectsE6;
use Bga\Games\projectedgeworth\Material\ConditionId;
use Bga\Games\projectedgeworth\Material\EffectKind;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\PartId;
use Bga\Games\projectedgeworth\Material\ProjectCards;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\Effects\E6Cards;
use ProjectEdgeworthTests\Support\Effects\FakeGame;
use ProjectEdgeworthTests\Support\SeededRandom;

/**
 * E-6（通信確立）14 種の定義が、002 のカードデータ・007 §10 の付表・011 §7 の表と一致すること。
 */
final class E6MaterialTest extends TestCase
{
    /**
     * @return array<string, array{string, string, Hook, string, string, string, list<string>}>
     *     カード => [ID, 窓の記号, フック, 費用の種類, actor, area, 007 §10 の資源]
     */
    public static function cards(): array
    {
        $ml = ['M-', 'L'];

        return [
            'P047' => ['P047', 'TE', Hook::Turned, 'SPECIAL', 'THIS', 'BELOW', $ml],
            'P050' => ['P050', 'KE', Hook::Linked, 'SPECIAL', 'OTHER_OWN', 'BELOW', $ml],
            'P051' => ['P051', 'KE', Hook::Linked, 'SPECIAL', 'OTHER_OWN', 'BELOW', $ml],
            'P056' => ['P056', 'KE', Hook::Linked, 'SPECIAL', 'THIS', 'AHEAD', $ml],
            'P057' => ['P057', 'KE', Hook::Linked, 'SPECIAL', 'THIS', 'BEHIND', $ml],
            'P058' => ['P058', 'KE', Hook::Linked, 'SPECIAL', 'THIS', 'BELOW', $ml],
            'P059' => ['P059', 'KE', Hook::Linked, 'SPECIAL', 'THIS', 'BELOW', $ml],
            'P060' => ['P060', 'KE', Hook::Linked, 'SPECIAL', 'THIS', 'ADJACENT_LAND_OF_ACTOR', $ml],
            'P061' => ['P061', 'KE', Hook::Linked, 'SPECIAL', 'THIS', 'ADJACENT_LAND_OF_ACTOR', $ml],
            'P076' => ['P076', 'PB', Hook::ProposalAfter, 'NORMAL', 'CHOSEN_OWN', 'ADJACENT_LAND_OF_ACTOR', ['H*', 'M-', 'L']],
            'P096' => ['P096', 'O1', Hook::OrbitEntered, 'SPECIAL', 'THIS', 'BELOW', $ml],
            'P097' => ['P097', 'O1', Hook::OrbitEntered, 'SPECIAL', 'THIS', 'BELOW', $ml],
            'P098' => ['P098', 'O1', Hook::OrbitEntered, 'SPECIAL', 'THIS', 'BEHIND', $ml],
            'P099' => ['P099', 'O1', Hook::OrbitEntered, 'SPECIAL', 'THIS', 'BELOW', $ml],
        ];
    }

    #[DataProvider('cards')]
    public function testDefinitionMatchesTheTables(string $id, string $window, Hook $hook, string $costKind, string $actor, string $area, array $touches): void
    {
        $spec = CardEffects::forProject($id);
        $this->assertNotNull($spec, $id);
        $this->assertSame($hook, $spec->hook);
        $this->assertSame($window, $spec->hook->windowSymbol());
        $this->assertSame(PartId::EstablishLink, $spec->part);
        $this->assertTrue($spec->optional, '任意（[Use] / [Skip]）');
        $this->assertSame($costKind, $spec->params['costKind']);
        $this->assertSame($actor, $spec->params['actor']);
        $this->assertSame($area, $spec->params['area']);
        $this->assertSame($touches, array_map(static fn($t): string => $t->symbol->value, $spec->touches));
        $this->assertSame($id === 'P076' ? 'DISCARD_COST' : '', $spec->cost?->kind->value ?? '');
        if ($id === 'P076') {
            $this->assertSame(2, $spec->cost?->amount, '手札を 2 枚捨てる');
        }
    }

    #[DataProvider('cards')]
    public function testKindAndActiveConditionAgreeWith002(string $id, mixed ...$rest): void
    {
        $card = ProjectCards::get($id);
        $spec = CardEffects::forProject($id);
        $this->assertNotNull($spec);
        $this->assertSame($card->effectKind->value, $spec->kind->value, $id);
        $this->assertSame(EffectKind::Activated, $spec->kind);
        $this->assertTrue($spec->activeOnly);
        $this->assertSame($card->activeCondition->value === 'ACTIVE_ONLY', $spec->activeOnly, $id);
    }

    public function testOnlyP076UsesTheNormalCostFaq30(): void
    {
        foreach (E6Cards::PROJECT_IDS as $id) {
            $this->assertSame($id === 'P076' ? 'NORMAL' : 'SPECIAL', CardEffects::forProject($id)?->params['costKind'], $id);
        }
    }

    public function testConditionsFollowTheTableAndAreSetOnTheRightHooks(): void
    {
        $regions = static function (string $id, ConditionId $kind): ?array {
            foreach (CardEffects::forProject($id)?->conditions ?? [] as $c) {
                if ($c->id === $kind) {
                    return $c->params['regions'] ?? [];
                }
            }

            return null;
        };
        // K04: 通信確立した区域の地域（FAQ 行 22・73）
        $this->assertSame(['A', 'C', 'E'], $regions('P050', ConditionId::LinkAreaRegion));
        $this->assertSame(['B', 'D', 'F'], $regions('P051', ConditionId::LinkAreaRegion));
        $this->assertSame(['C', 'E'], $regions('P058', ConditionId::LinkAreaRegion));
        $this->assertSame(['D', 'F'], $regions('P059', ConditionId::LinkAreaRegion));
        $this->assertSame(['A', 'G'], $regions('P060', ConditionId::LinkAreaRegion));
        $this->assertSame(['B', 'H'], $regions('P061', ConditionId::LinkAreaRegion));
        $this->assertNull($regions('P056', ConditionId::LinkAreaRegion));
        // K08: 別地域からの突入
        $this->assertSame(['A', 'C', 'F'], $regions('P096', ConditionId::RegionChanged));
        $this->assertSame(['B', 'D', 'G'], $regions('P097', ConditionId::RegionChanged));
        $this->assertSame([], $regions('P099', ConditionId::RegionChanged));
        $this->assertNull($regions('P098', ConditionId::RegionChanged));
        // K07: 自分の通信確立済みの区域への突入（P098）
        $this->assertSame([], $regions('P098', ConditionId::EnteredOwnLinkedArea));
        // K01: P058・P059 だけ「自分の他の衛星」、それ以外はこの衛星
        foreach (E6Cards::PROJECT_IDS as $id) {
            if ($id === 'P076') {
                $this->assertNull($regions($id, ConditionId::EventSatellite), 'P076 は窓のフック（企画）で衛星の条件なし');
                continue;
            }
            $scope = null;
            foreach (CardEffects::forProject($id)?->conditions ?? [] as $c) {
                if ($c->id === ConditionId::EventSatellite) {
                    $scope = $c->params['scope'];
                }
            }
            $this->assertSame(in_array($id, ['P058', 'P059'], true) ? 'OTHER_OWN' : 'THIS', $scope, $id);
        }
    }

    public function testThereAre14CardsInCardEffectsE6(): void
    {
        $keys = E6Cards::keys();
        $this->assertCount(14, $keys);
        $this->assertCount(14, array_unique($keys));
        $this->assertCount(14, CardEffectsE6::specs());
        $this->assertEqualsCanonicalizing($keys, array_map(static fn($s): string => $s->cardKey, CardEffectsE6::specs()));
        foreach ($keys as $key) {
            $this->assertNotNull(CardEffects::get($key), $key);
        }
    }

    public function testPartsRegistration(): void
    {
        $game = new FakeGame();
        $layout = BoardLayoutBuilder::build(BoardFace::STANDARD, BoardPlacement::EARTH, 4, new SeededRandom(1));
        $parts = PartsE1::register(new PartRegistry(), $game, $layout);
        $modifiers = new ModifierQuery($game, new CardEffectCatalog(), $parts, new ConditionEvaluator());
        $queries = new EffectQueries($modifiers);
        $registry = PartsE6::register($parts, $queries, new UseMods($modifiers, $queries, $game, $game), $layout);
        $this->assertTrue($registry->has(PartId::EstablishLink));
    }
}
