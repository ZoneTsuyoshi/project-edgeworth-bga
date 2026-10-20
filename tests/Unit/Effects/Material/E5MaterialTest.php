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
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartsE5;
use Bga\Games\ProjectEdgeworth\Effects\Query\EffectQueries;
use Bga\Games\ProjectEdgeworth\Effects\Query\ModifierQuery;
use Bga\Games\ProjectEdgeworth\Effects\Query\UseMods;
use Bga\Games\ProjectEdgeworth\Material\CardEffectCatalog;
use Bga\Games\ProjectEdgeworth\Material\CardEffects;
use Bga\Games\ProjectEdgeworth\Material\CardEffectsE5;
use Bga\Games\ProjectEdgeworth\Material\Hook;
use Bga\Games\ProjectEdgeworth\Material\PartId;
use Bga\Games\ProjectEdgeworth\Material\ProjectCards;
use Bga\Games\ProjectEdgeworth\Material\SatelliteCards;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\Effects\E5Cards;
use ProjectEdgeworthTests\Support\Effects\FakeGame;
use ProjectEdgeworthTests\Support\SeededRandom;

/**
 * E-5（配備）6 種の定義が、002 のカードデータ・007 §10 の付表・011 §7 の表と一致すること。
 */
final class E5MaterialTest extends TestCase
{
    /**
     * @return array<string, array{string, string, Hook, PartId, bool, string, list<string>}>
     *     カード => [ID または name_id, 窓の記号, フック, 部品, 任意か, 支払い（なしは ''）, 007 §10 の資源]
     */
    public static function cards(): array
    {
        return [
            'P011' => ['P011', 'DE', Hook::Deployed, PartId::DeployFromHand, true, '', ['M-', 'H*', 'C']],
            'P022' => ['P022', 'DE', Hook::Deployed, PartId::RearrangeStandby, false, '', ['C']],
            'P035' => ['P035', 'LE', Hook::Launched, PartId::DeployDeckTop, false, '', ['C', 'Cr']],
            'P045' => ['P045', 'TE', Hook::Turned, PartId::DeployFromHand, true, '', ['M-', 'H*', 'C']],
            'P074' => ['P074', 'PB', Hook::ProposalAfter, PartId::DeployFromHand, true, 'DISCARD_COST', ['M-', 'H*', 'C']],
            'R005' => ['stealth_moon', 'LE', Hook::Launched, PartId::DeployFromHand, true, '', ['M-', 'H*', 'C']],
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
            // R005 STEALTH MOON: 打ち上げ時（002 §6.1）、稼働条件なし
            $this->assertSame('ON_LAUNCH', $spec->kind->value);
            $this->assertFalse($spec->activeOnly);
        }
    }

    public function testThereAre6CardsAndFourAreInCardEffectsE5(): void
    {
        $keys = E5Cards::keys();
        $this->assertCount(6, $keys);
        $this->assertCount(6, array_unique($keys));
        // P011・P035 は E-0 の代表として CardEffects が先に持つので、CardEffectsE5 は残りの 4 種
        $this->assertCount(4, CardEffectsE5::specs());
        foreach ($keys as $key) {
            $this->assertNotNull(CardEffects::get($key), $key);
        }
        $names = array_map(static fn($c): string => $c->nameId, SatelliteCards::all());
        foreach (E5Cards::SATELLITE_KEYS as $key) {
            $this->assertContains($key, $names);
        }
    }

    public function testSurchargeAndMaxCardsFollowFaq20And69(): void
    {
        // FAQ 行 20: P011・P045・P074 は稼働中の衛星への追加配備コスト（+5）が必要。FAQ 行 69: R005 は不要で、最大 2 枚
        foreach (['P011', 'P045', 'P074'] as $id) {
            $this->assertTrue(CardEffects::forProject($id)?->params['surcharge'], $id);
        }
        $r005 = CardEffects::get('stealth_moon');
        $this->assertFalse($r005?->params['surcharge']);
        $this->assertSame(2, $r005?->params['maxCards']);
        $this->assertSame('THIS', $r005?->params['targetScope']);
        $this->assertSame('ANY_OWN', CardEffects::forProject('P011')?->params['targetScope']);
        $this->assertSame('DEVICE', CardEffects::forProject('P011')?->params['filterType']);
        $this->assertSame('ANY_OWN', CardEffects::forProject('P074')?->params['targetScope']);
        $this->assertSame('THIS', CardEffects::forProject('P045')?->params['targetScope']);
        // P035 は配備コストなし（追加配備コストも不要）
        $this->assertTrue(CardEffects::forProject('P035')?->params['free']);
    }

    public function testPartsRegistration(): void
    {
        $game = new FakeGame();
        $layout = BoardLayoutBuilder::build(BoardFace::STANDARD, BoardPlacement::EARTH, 4, new SeededRandom(1));
        $parts = PartsE1::register(new PartRegistry(), $game, $layout);
        $modifiers = new ModifierQuery($game, new CardEffectCatalog(), $parts, new ConditionEvaluator());
        $queries = new EffectQueries($modifiers);
        $registry = PartsE5::register($parts, $queries, new UseMods($modifiers, $queries, $game, $game));
        foreach ([PartId::DeployFromHand, PartId::DeployDeckTop, PartId::RearrangeStandby] as $id) {
            $this->assertTrue($registry->has($id), $id->value);
        }
    }
}
