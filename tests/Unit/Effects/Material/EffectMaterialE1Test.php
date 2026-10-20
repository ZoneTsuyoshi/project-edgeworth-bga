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
use Bga\Games\projectedgeworth\Effects\Parts\PartRegistry;
use Bga\Games\projectedgeworth\Effects\Parts\PartsE1;
use Bga\Games\projectedgeworth\Material\ActiveCondition;
use Bga\Games\projectedgeworth\Material\CardEffects;
use Bga\Games\projectedgeworth\Material\EffectSpec;
use Bga\Games\projectedgeworth\Material\ProjectCards;
use Bga\Games\projectedgeworth\Material\SatelliteCards;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\Effects\FakeGame;
use ProjectEdgeworthTests\Support\SeededRandom;

/**
 * E-1（常時の修正・禁止）35 種の `EffectSpec` が、002 のカード定義と 007 §10 の付表（窓・選択・資源）、011 §7 の表（部品・
 * パラメータ・条件）と一致すること。`EffectMaterialTest`（E-0 の代表 10 枚）と同じ照合を E-1 の分に広げたもの。
 */
final class EffectMaterialE1Test extends TestCase
{
    private const E1_CARDS = [
        'P008', 'P010', 'P012', 'P014', 'P015', 'P016', 'P017', 'P018', 'P026', 'P033', 'P040', 'P046', 'P052', 'P053', 'P054',
        'P055', 'P062', 'P063', 'P064', 'P065', 'P066', 'P067', 'P071', 'P073', 'P077', 'P079', 'P080', 'P085', 'P094',
        'R006', 'R007', 'R008', 'R009', 'R011', 'R014',
    ];

    /**
     * @return array<string, array{string, string, ?string, string, string, array<string, mixed>, list<string>, list<string>}>
     */
    public static function e1Cards(): array
    {
        $rows = [];
        foreach (self::table() as $cardId => $row) {
            $rows[$cardId] = [$cardId, ...$row];
        }

        return $rows;
    }

    /**
     * 011 §7 の表と 007 §10 の付表を書き起こした期待値。
     *
     * @return array<string, array{string, ?string, string, string, array<string, mixed>, list<string>, list<string>}>
     *     カード ID => [フック, 007 の窓・時点（禁止は null）, 効果タイプ, 部品, パラメータ, 条件, 007 §10 の資源]
     */
    private static function table(): array
    {
        $dc = 'DEPLOY_COST';
        $lt = 'LINK_TARGETS';
        $pc = 'PROPOSAL_COUNTS';
        $here = ['scope' => 'THIS_SATELLITE'];

        return [
            'P008' => ['BUDGET_MAIN_GAIN', 'B1', 'CONSTANT', 'BUDGET_GAIN_MOD', ['perFullSatellite' => 2], [], []],
            'P010' => [$dc, 'DC', 'CONSTANT', 'DEPLOY_COST_MOD', ['delta' => -2, ...$here], [], []],
            'P012' => [$dc, 'DC', 'CONSTANT', 'DEPLOY_COST_MOD', ['delta' => -3, ...$here], [], []],
            'P014' => [$dc, 'DC', 'CONSTANT', 'DEPLOY_COST_MOD', ['delta' => -3, ...$here, 'type' => 'CREW'], [], []],
            'P015' => [$dc, 'DC', 'CONSTANT', 'DEPLOY_COST_MOD', ['delta' => -3, ...$here, 'type' => 'DEVICE'], [], []],
            'P016' => [$dc, 'DC', 'CONSTANT', 'DEPLOY_COST_MOD', ['delta' => -3, ...$here, 'type' => 'SOFTWARE'], [], []],
            'P017' => [$dc, 'DC', 'CONSTANT', 'DEPLOY_COST_MOD', ['delta' => -4, ...$here, 'type' => 'DEVICE'], [], []],
            'P018' => [$dc, 'DC', 'CONSTANT', 'DEPLOY_COST_MOD', ['delta' => -1, 'scope' => 'ALL_OWN'], [], []],
            'P026' => [$dc, 'DC', 'ON_DEPLOY', 'DEPLOY_COST_MOD', ['delta' => -2, 'scope' => 'SELF_CARD', 'perAttachedCard' => true], [], ['Cr']],
            'P033' => ['ALLOWED', null, 'CONSTANT', 'FORBID', ['action' => 'DEPLOY_TO_THIS'], [], []],
            'P040' => ['LAUNCH_COST', 'LC', 'CONSTANT', 'LAUNCH_COST_MOD', ['delta' => -3, 'scope' => 'ALL_OWN'], [], []],
            'P046' => ['ALLOWED', null, 'CONSTANT', 'FORBID', ['action' => 'TURN_THIS'], [], []],
            'P052' => [$lt, 'KC', 'CONSTANT', 'LINK_TARGET_EXPAND', ['shape' => 'ADJACENT_LAND'], [], []],
            'P053' => [$lt, 'KC', 'CONSTANT', 'LINK_TARGET_EXPAND', ['shape' => 'RIGHT2'], [], []],
            'P054' => [$lt, 'KC', 'CONSTANT', 'LINK_TARGET_EXPAND', ['shape' => 'LEFT2'], [], []],
            'P055' => ['LINK_PIONEER', 'KC', 'CONSTANT', 'PIONEER_WAIVER', [], [], []],
            'P062' => [$lt, 'KC', 'CONSTANT', 'LINK_TARGET_EXPAND', ['shape' => 'ADJACENT_SEA'], ['BELOW_REGION:E,G'], []],
            'P063' => [$lt, 'KC', 'CONSTANT', 'LINK_TARGET_EXPAND', ['shape' => 'ADJACENT_SEA'], ['BELOW_REGION:F,H'], []],
            'P064' => [$lt, 'KC', 'CONSTANT', 'LINK_TARGET_EXPAND', ['shape' => 'FRONT3'], [], []],
            'P065' => [$lt, 'KC', 'CONSTANT', 'LINK_TARGET_EXPAND', ['shape' => 'BACK3'], [], []],
            'P066' => ['ALLOWED', null, 'CONSTANT', 'FORBID', ['action' => 'LINK_THIS'], [], []],
            'P067' => [$pc, 'PA', 'CONSTANT', 'PROPOSAL_COUNT_MOD', ['mainDraw' => 1, 'bonusDraw' => 1], [], []],
            'P071' => [$pc, 'PA', 'CONSTANT', 'PROPOSAL_COUNT_MOD', ['bonusDraw' => 1, 'bonusKeep' => 1], [], []],
            'P073' => [$pc, 'PA', 'CONSTANT', 'PROPOSAL_COUNT_MOD', ['keep' => 1], [], []],
            'P077' => ['SELL_PRICE', 'AN', 'CONSTANT', 'SELL_PRICE_MOD', ['delta' => 2], [], []],
            'P079' => ['FUEL_CONSUME', 'O2', 'CONSTANT', 'FUEL_CONSUME_MOD', [], ['FULL_TYPES', 'BELOW_REGION:A,H'], []],
            'P080' => ['FUEL_CONSUME', 'O2', 'CONSTANT', 'FUEL_CONSUME_MOD', [], ['FULL_TYPES', 'BELOW_REGION:B,E'], []],
            'P085' => ['NEAR_MISS', 'O3', 'CONSTANT', 'NEAR_MISS_FUEL_MOD', ['value' => 1], [], []],
            'P094' => ['NEAR_MISS', 'O3', 'CONSTANT', 'NEAR_MISS_SUPPRESS', [], ['FULL_TYPES'], []],
            'R006' => ['LAUNCH_COST', 'LC', 'CONSTANT', 'LAUNCH_COST_MOD', ['delta' => -3, 'scope' => 'ALL_OWN'], [], []],
            'R007' => [$dc, 'DC', 'CONSTANT', 'DEPLOY_COST_MOD', ['delta' => -1, 'scope' => 'ALL_OWN'], [], []],
            'R008' => ['LINK_COST', 'KC', 'CONSTANT', 'LINK_COST_MOD', ['delta' => -1, 'scope' => 'ALL_OWN'], [], []],
            'R009' => ['SELL_PRICE', 'AN', 'CONSTANT', 'SELL_PRICE_MOD', ['delta' => 2], [], []],
            'R011' => [$pc, 'PA', 'ACTIVATED', 'PROPOSAL_COUNT_MOD', ['bonusBase' => ['draw' => 6, 'keep' => 3]], ['OWN_ACTION_CHOICE'], []],
            'R014' => [$lt, 'KC', 'ACTIVATED', 'LINK_TARGET_EXPAND', ['shape' => 'ALL_ADJACENT6', 'bonusOnly' => true], [], []],
        ];
    }

    private function specFor(string $cardId): ?EffectSpec
    {
        if ($cardId[0] === 'P') {
            return CardEffects::forProject($cardId);
        }
        foreach (SatelliteCards::all() as $id => $card) {
            if ($id === $cardId) {
                return CardEffects::get($card->nameId);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $params
     * @param list<string> $conditions
     * @param list<string> $touches
     */
    #[DataProvider('e1Cards')]
    public function testSpecMatchesThe002CardDataAnd007TableAnd011Row(
        string $cardId,
        string $hook,
        ?string $window,
        string $kind,
        string $part,
        array $params,
        array $conditions,
        array $touches,
    ): void {
        $spec = $this->specFor($cardId);
        $this->assertNotNull($spec, $cardId);

        // 002: 効果タイプ・稼働条件が一致する
        if ($cardId[0] === 'P') {
            $definition = ProjectCards::get($cardId);
            $this->assertSame($definition->nameId, $spec->cardKey);
        } else {
            $definition = SatelliteCards::all()[$cardId];
            $this->assertSame($definition->nameId, $spec->cardKey);
        }
        $this->assertSame($definition->effectKind, $spec->kind, "$cardId 効果タイプ");
        $this->assertSame($definition->activeCondition === ActiveCondition::ActiveOnly, $spec->activeOnly, "$cardId 稼働条件");

        // 011 §7 の表: フック・部品・パラメータ・条件。007 §10: 窓・資源
        $this->assertSame($hook, $spec->hook->value, "$cardId フック");
        $this->assertSame($window, $spec->hook->windowSymbol(), "$cardId 007 の窓");
        $this->assertSame($kind, $spec->kind->value, "$cardId 効果タイプ");
        $this->assertSame($part, $spec->part->value, "$cardId 部品");
        $this->assertEquals($params, $spec->params, "$cardId パラメータ");
        $this->assertSame($conditions, array_map(
            static fn($c): string => $c->id->value . ($c->params === [] ? '' : ':' . implode(',', $c->params['regions'])),
            $spec->conditions,
        ), "$cardId 条件");
        $this->assertSame($touches, array_map(static fn($t): string => $t->symbol->value, $spec->touches), "$cardId 資源");

        // 修正・禁止は問い合わせフックで、選択（[Use] / [Skip]）も支払いも段もない
        $this->assertTrue($spec->isQuery(), "$cardId は問い合わせフック");
        $this->assertFalse($spec->isTriggered());
        $this->assertFalse($spec->optional, "$cardId 007 の選択は「—」");
        $this->assertNull($spec->cost);
        $this->assertSame(1, $spec->stages);
        $this->assertSame($spec->kind->value === 'ON_DEPLOY', $spec->selfOnly, "$cardId selfOnly");
    }

    public function testTheE1RowsAreExactlyThe35CardsOfSection8(): void
    {
        $this->assertCount(35, self::E1_CARDS);
        $this->assertSame(self::E1_CARDS, array_keys(self::table()));
        foreach (self::E1_CARDS as $cardId) {
            $this->assertNotNull($this->specFor($cardId), "$cardId の定義がある");
        }
    }

    public function testEveryE1PartIsRegisteredByPartsE1(): void
    {
        $game = new FakeGame();
        $layout = BoardLayoutBuilder::build(BoardFace::STANDARD, BoardPlacement::EARTH, 4, new SeededRandom(1));
        $registry = PartsE1::register(new PartRegistry(), $game, $layout);

        foreach (self::E1_CARDS as $cardId) {
            $part = $this->specFor($cardId)?->part;
            $this->assertNotNull($part);
            $this->assertTrue($registry->has($part), "$cardId の部品 {$part->value} が登録されている");
        }
        // E-1 の部品は M01〜M06・M09〜M13 と F01 の 12 種（M07・M08 は E-3）
        $registered = array_filter(\Bga\Games\projectedgeworth\Material\PartId::cases(), static fn($p): bool => $registry->has($p));
        $this->assertCount(12, $registered);
    }
}
