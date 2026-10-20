<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects\Cards;

use Bga\Games\ProjectEdgeworth\Effects\Bonus\BonusDeclaration;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameEvent;
use Bga\Games\ProjectEdgeworth\Effects\EffectEngine;
use Bga\Games\ProjectEdgeworth\Effects\EffectViolation;
use Bga\Games\ProjectEdgeworth\Effects\Query\ProposalOption;
use Bga\Games\ProjectEdgeworth\Effects\Query\QueryContext;
use Bga\Games\ProjectEdgeworth\Effects\Question\Answer;
use Bga\Games\ProjectEdgeworth\Effects\Question\FieldType;
use Bga\Games\ProjectEdgeworth\Effects\Question\Question;
use Bga\Games\ProjectEdgeworth\Flow\Action\ProposalCounts;
use Bga\Games\ProjectEdgeworth\Flow\Action\StepMode;
use Bga\Games\ProjectEdgeworth\Material\ActionKind;
use Bga\Games\ProjectEdgeworth\Material\Hook;
use Bga\Games\ProjectEdgeworth\Material\ProjectType;
use Bga\Games\ProjectEdgeworth\Material\SatelliteShape;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\Effects\E3Fixture;

/**
 * 効果 E-3（任意の効果と問い）22 種の結線テスト: 本物の `CardEffects`・部品・条件・盤面で、[Use] / [Skip] と支払い（C1）、
 * 起動の修正（`USE_MODS`）、企画のオプション、2 段の効果（P087）、EUROPA の宣言を確かめる（011 §6・§7・§11-3 の T02・T14・T19・T20・T24・T25・T30）。
 */
final class E3EffectsTest extends TestCase
{
    private const P1 = 1;
    private const P2 = 2;

    /** @param list<Question> $questions */
    private function ask(array $questions, string $cardKey): Question
    {
        foreach ($questions as $q) {
            if ($q->cardKey === $cardKey) {
                return $q;
            }
        }
        $this->fail("no question for $cardKey");
    }

    private function entryOf(EffectEngine $engine, int $player, string $cardKey): int
    {
        return (int) $this->ask($engine->questionsFor($player), $cardKey)->entryId;
    }

    private function budgetFree(): GameEvent
    {
        return new GameEvent(Hook::BudgetFree, null);
    }

    // ---- P005 調達担当官 ----

    public function testP005AsksUseOrSkipAndPaysFourMayaToDrawOne(): void
    {
        $f = new E3Fixture(10);
        $f->satellite(self::P1, ['P005']);
        $engine = $f->engine();

        $report = $engine->openWindow('B3', [$this->budgetFree()]);
        $this->assertSame([self::P1], $report->playersNeedingInput);
        $q = $this->ask($engine->questionsFor(self::P1), 'procurement_officer');
        $this->assertFalse($q->mandatory);
        $this->assertSame(['kind' => 'PAY_MAYA', 'amount' => 4, 'canPay' => true, 'shortfall' => 0, 'canPayBySelling' => true], $q->cost);

        $engine->useEffect(self::P1, (int) $q->entryId, new Answer());

        $this->assertSame(6, $f->game->maya[self::P1]);
        $this->assertSame([101], $f->game->hand[self::P1]);
        $this->assertSame(['P:5' => true], $f->game->used);
        $this->assertSame(1, $engine->closeWindow()->resolvedCount);
    }

    public function testP005SkipKeepsItUnusedAndCannotBeUsedWithoutMayaUnlessSold(): void
    {
        $f = new E3Fixture(1);
        $f->game->hand[self::P1] = [50, 51];
        $f->satellite(self::P1, ['P005']);
        $engine = $f->engine();
        $engine->openWindow('B3', [$this->budgetFree()]);
        $id = $this->entryOf($engine, self::P1, 'procurement_officer');

        $q = $this->ask($engine->questionsFor(self::P1), 'procurement_officer');
        $this->assertFalse($q->cost['canPay']);
        $this->assertSame(3, $q->cost['shortfall']);
        $this->assertTrue($q->cost['canPayBySelling']);

        try {
            $engine->useEffect(self::P1, $id, new Answer());
            $this->fail('1 マヤでは払えない');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        // 売却を同梱: 1 + 3 = 4 を払って 0 に
        $engine->useEffect(self::P1, $id, new Answer([], [50]));
        $this->assertSame(0, $f->game->maya[self::P1]);
        $this->assertSame([51, 101], $f->game->hand[self::P1]);

        $g = new E3Fixture(10);
        $g->satellite(self::P1, ['P005']);
        $skip = $g->engine();
        $skip->openWindow('B3', [$this->budgetFree()]);
        $skip->skipEffect(self::P1, $this->entryOf($skip, self::P1, 'procurement_officer'));
        $this->assertSame([], $g->game->used);
        $this->assertSame(10, $g->game->maya[self::P1]);
    }

    public function testP005NeedsAnActiveSatellite(): void
    {
        $f = new E3Fixture(10);
        $f->satellite(self::P1, ['P005'], false);

        $this->assertFalse($f->engine()->openWindow('B3', [$this->budgetFree()])->needsInput());
    }

    // ---- P006 予算折衝官（T02・S01）----

    public function testP006ConflictsWithR010AndTheOrderDecidesWhetherItCanBePaid(): void
    {
        $f = new E3Fixture(3);
        $f->satellite(self::P1, ['P006'], true, 'stealth_phobos');
        $engine = $f->engine();
        $engine->openWindow('B0', [new GameEvent(Hook::BudgetStart, null)]);

        $questions = $engine->questionsFor(self::P1);
        $this->assertSame(['ORDER'], array_map(static fn(Question $q): string => $q->type->value, $questions));
        $order = $questions[0];
        $this->assertEqualsCanonicalizing(['budget_negotiator', 'stealth_phobos'], array_column($order->entries, 'cardKey'));
        $this->assertSame(['stealth_phobos' => true, 'budget_negotiator' => false], array_column($order->entries, 'forced', 'cardKey'));
        $ids = array_column($order->entries, 'entryId', 'cardKey');

        // R010 を先に: 3 + 2 = 5 になって P006（5 マヤ）が払える
        $engine->orderEffects(self::P1, [$ids['stealth_phobos'], $ids['budget_negotiator']]);
        $q = $this->ask($engine->questionsFor(self::P1), 'budget_negotiator');
        $this->assertTrue($q->cost['canPay']);
        $engine->useEffect(self::P1, (int) $q->entryId, new Answer());

        $this->assertSame(0, $f->game->maya[self::P1]);
        $this->assertSame(1, $f->game->budget[self::P1], '予算 +1 は B1 の獲得額に反映される');
    }

    public function testP006FirstThenR010CannotPayWith3Maya(): void
    {
        $f = new E3Fixture(3);
        $f->satellite(self::P1, ['P006'], true, 'stealth_phobos');
        $engine = $f->engine();
        $engine->openWindow('B0', [new GameEvent(Hook::BudgetStart, null)]);
        $ids = array_column($engine->questionsFor(self::P1)[0]->entries, 'entryId', 'cardKey');

        $engine->orderEffects(self::P1, [$ids['budget_negotiator'], $ids['stealth_phobos']]);

        $q = $this->ask($engine->questionsFor(self::P1), 'budget_negotiator');
        $this->assertFalse($q->cost['canPay']);
        $this->assertSame(2, $q->cost['shortfall']);
    }

    // ---- 起動の修正（USE_MODS）: P019〜P021・P036・R021〜R025 ----

    public function testP019OffersTheUseModsOnlyForCrewAndOnlyWhileActive(): void
    {
        $f = new E3Fixture();
        $sat = $f->satellite(self::P1, ['P019']);
        $other = $f->satellite(self::P1);

        $crew = new QueryContext(self::P1, $other, 13, ProjectType::Crew);
        $options = $f->useMods->options(Hook::DeployCost, $crew);
        $this->assertSame([['hostKey' => 'P:19', 'cardKey' => 'fleet_commander', 'reduction' => 2]], $options, '全衛星の CREW に効く');
        $this->assertSame([], $f->useMods->options(Hook::DeployCost, new QueryContext(self::P1, $other, 34, ProjectType::Device)));
        $this->assertSame([], $f->useMods->options(Hook::DeployCost, new QueryContext(self::P2, $other, 13, ProjectType::Crew)), '他のプレイヤーの衛星には効かない');

        $f->game->setActive($sat, false);
        $this->assertSame([], $f->useMods->options(Hook::DeployCost, $crew), 'ACTIVE_ONLY');
    }

    public function testP019P020P021DiscountTheirOwnTypeByTwoWhenUsed(): void
    {
        $f = new E3Fixture();
        $sat = $f->satellite(self::P1, ['P019', 'P020', 'P021']);
        foreach ([[ProjectType::Crew, 13, 'P:19'], [ProjectType::Device, 34, 'P:20'], [ProjectType::Software, 67, 'P:21']] as [$type, $no, $key]) {
            $q = new QueryContext(self::P1, $sat, $no, $type);
            $this->assertSame([$key], array_column($f->useMods->options(Hook::DeployCost, $q), 'hostKey'));
            $quote = $f->useMods->quote(Hook::DeployCost, $q, 6);
            $this->assertSame(6, $quote['without']);
            $this->assertSame([$key => 4], $quote['with']);
        }
    }

    public function testT19TheCostNeverGoesBelowZeroAfterAllTheModifiers(): void
    {
        $f = new E3Fixture();
        $sat = $f->satellite(self::P1, ['P019', 'P010']);
        $q = new QueryContext(self::P1, $sat, 13, ProjectType::Crew);

        $this->assertSame(0, $f->useMods->quote(Hook::DeployCost, $q, 3)['all'], '3 - 2(P010) - 2(P019) は 0 で止まる');
    }

    public function testT20AFleetModifierWorksOncePerRoundAndTheChoiceIsValidated(): void
    {
        $f = new E3Fixture();
        $sat = $f->satellite(self::P1, ['P019']);
        $q = new QueryContext(self::P1, $sat, 13, ProjectType::Crew);
        $options = $f->useMods->options(Hook::DeployCost, $q);

        $field = $f->useMods->field($options);
        $this->assertSame(FieldType::UseMods, $field->type);
        $this->assertSame(['P:19'], $field->candidates);
        $this->assertSame(['P:19'], $f->useMods->validate(['P:19'], $options));
        $this->assertSame([], $f->useMods->validate([], $options), '使わない');
        foreach ([['P:20'], ['P:19', 'P:19'], [19]] as $bad) {
            try {
                $f->useMods->validate($bad, $options);
                $this->fail('不正な useMods');
            } catch (EffectViolation) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            $f->useMods->validateStep([['P:19'], ['P:19']]);
            $this->fail('同じステップの 2 つの選択に同じカードは使えない');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        $f->useMods->validateStep([['P:19'], []]);

        // コミットで使用済みになり、メインで使ったらボーナスでは候補に出ない
        $this->assertSame([], $f->game->used);
        $f->useMods->commit(['P:19']);
        $this->assertSame(['P:19' => true], $f->game->used);
        $this->assertSame([], $f->useMods->options(Hook::DeployCost, $q));
    }

    public function testUseModsReachTheActionHooksOnlyWhenChosen(): void
    {
        $f = new E3Fixture();
        $sat = $f->satellite(self::P1, ['P019']);
        $row = $f->row($sat, 5);

        $this->assertSame(0, $f->hooks->deployCostReduction(self::P1, $row, 13, 0), '使わなければ畳み込まない');
        $f->hooks->useMods(['P:19']);
        $this->assertSame(2, $f->hooks->deployCostReduction(self::P1, $row, 13, 0));
        $this->assertSame(0, $f->hooks->deployCostReduction(self::P1, $row, 34, 0), 'DEVICE には効かない');
        $f->hooks->useMods([]);
        $this->assertSame(0, $f->hooks->deployCostReduction(self::P1, $row, 13, 0));
    }

    public function testP036DiscountsOnePerDeployedCardOfTheLaunchingSatelliteEvenWhenItIsNotActive(): void
    {
        $f = new E3Fixture();
        $sat = $f->satellite(self::P1, ['P036', 'P010', 'P013'], false);
        $q = new QueryContext(self::P1, $sat, attachedCount: $f->game->cardsOn($sat));

        $this->assertSame([['hostKey' => 'P:36', 'cardKey' => 'lightweight_frame', 'reduction' => 3]], $f->useMods->options(Hook::LaunchCost, $q));
        $row = $f->row($sat, 0, false);
        $this->assertSame(0, $f->hooks->launchCostReduction(self::P1, $row));
        $f->hooks->useMods(['P:36']);
        $this->assertSame(3, $f->hooks->launchCostReduction(self::P1, $row), 'このカード自身も配備されたカードとして数える');

        $other = $f->satellite(self::P1, ['P010']);
        $this->assertSame(0, $f->hooks->launchCostReduction(self::P1, $f->row($other, 0, false)), '打ち上げる衛星に付いたカードだけ（scope = LAUNCHING_SATELLITE）');
    }

    public function testP036IsUsedOncePerRound(): void
    {
        $f = new E3Fixture();
        $sat = $f->satellite(self::P1, ['P036']);
        $q = new QueryContext(self::P1, $sat, attachedCount: 1);
        $f->useMods->commit(['P:36']);

        $this->assertSame([], $f->useMods->options(Hook::LaunchCost, $q));
    }

    public function testGanymedeModifiersDependOnTheRegionOrKindOfTheLinkedArea(): void
    {
        $f = new E3Fixture();
        $hammer = $f->satellite(self::P1, [], true, 'hammer_ganymede');
        $retro = $f->satellite(self::P1, [], true, 'retro_ganymede', SatelliteShape::Retro);
        $scallop = $f->satellite(self::P1, [], true, 'scallop_ganymede', SatelliteShape::Scallop);
        $concorde = $f->satellite(self::P1, [], true, 'concorde_ganymede', SatelliteShape::Concorde);
        $stealth = $f->satellite(self::P1, [], true, 'stealth_ganymede', SatelliteShape::Stealth);

        $reduction = static function (E3Fixture $f, int $sat, int $cell): int {
            return array_sum(array_column($f->useMods->options(Hook::LinkCost, new QueryContext(self::P1, $sat, cell: $cell)), 'reduction'));
        };
        // R021: A・C・E・G、R022: B・D・F・H（通信確立する区域の地域。FAQ 行 73）
        $this->assertSame(2, $reduction($f, $hammer, $f->cell('A', true)));
        $this->assertSame(2, $reduction($f, $hammer, $f->cell('G', false)));
        $this->assertSame(0, $reduction($f, $hammer, $f->cell('B', true)));
        $this->assertSame(2, $reduction($f, $retro, $f->cell('B', false)));
        $this->assertSame(0, $reduction($f, $retro, $f->cell('A', false)));
        // R023: 陸で -3、R024: 海で -2、R025: 常に -2
        $this->assertSame(3, $reduction($f, $scallop, $f->cell('C', true)));
        $this->assertSame(0, $reduction($f, $scallop, $f->cell('C', false)));
        $this->assertSame(2, $reduction($f, $concorde, $f->cell('C', false)));
        $this->assertSame(0, $reduction($f, $concorde, $f->cell('C', true)));
        $this->assertSame(2, $reduction($f, $stealth, $f->cell('D', true)));
        $this->assertSame(2, $reduction($f, $stealth, $f->cell('D', false)));
    }

    public function testGanymedeAppliesOnlyToTheLinkingSatelliteAndAlsoToSpecialLinks(): void
    {
        $f = new E3Fixture();
        $sat = $f->satellite(self::P1, [], true, 'stealth_ganymede');
        $other = $f->satellite(self::P1);
        $cell = $f->cell('A', true);

        $this->assertSame([], $f->useMods->options(Hook::LinkCost, new QueryContext(self::P1, $other, cell: $cell)), 'この衛星の通信確立にだけ');
        // 特殊通信確立（+5 は下限 0 の後。FAQ 行 74）にも同じ修正が効く
        $cost = $f->queries->linkCost(new QueryContext(self::P1, $sat, cell: $cell), 1, ['S:' . $sat]);
        $this->assertSame(5, $cost->withAfterFloor('special', 5)->total());
        $this->assertSame(0, $f->hooks->linkCostReduction(self::P1, $f->row($sat, $cell), $cell));
        $f->hooks->useMods(['S:' . $sat]);
        $this->assertSame(2, $f->hooks->linkCostReduction(self::P1, $f->row($sat, $cell), $cell));
    }

    // ---- 企画のオプション: P068・P069・P070・P072 ----

    public function testP069PaysFourMayaAfterTheProposalForBudgetPlusOne(): void
    {
        $f = new E3Fixture(10);
        $f->satellite(self::P1, ['P069']);
        $engine = $f->engine();

        $engine->openWindow('PB', [new GameEvent(Hook::ProposalAfter, null)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'roscosmos');
        $this->assertSame(4, $q->cost['amount']);
        $engine->useEffect(self::P1, (int) $q->entryId, new Answer());

        $this->assertSame(6, $f->game->maya[self::P1]);
        $this->assertSame(1, $f->game->budget[self::P1]);
    }

    public function testP070OffersTheExtraDiscardAndAnyHandCardWorksWithoutTheSellPriceBonus(): void
    {
        $f = new E3Fixture(0);
        $f->game->hand[self::P1] = [60, 61];
        $f->game->sellPrice = 7; // P077 + R009 相当。捨てる額には適用しない（FAQ 行 31・70）
        $f->satellite(self::P1, ['P070']);
        $plan = $f->proposal->plan(self::P1, StepMode::Main);

        $this->assertCount(1, $plan->options);
        $this->assertSame(ProposalOption::EXTRA_DISCARD, $plan->options[0]->kind);
        $this->assertSame(7, $plan->options[0]->reward);

        // 企画中に引いたカード（101）は残し、企画中に引いていない手札の 60 を捨てる（FAQ 行 28）
        $f->proposal->validate(self::P1, $plan, 1, [101, 102], [101], [], ['P:70' => 60]);
        $f->game->hand[self::P1][] = 101;
        $f->proposal->apply(self::P1, $plan, [], ['P:70' => 60]);

        $this->assertSame(7, $f->game->maya[self::P1], '+7 だけ。売却額（7）は適用しない');
        $this->assertSame([61, 101], $f->game->hand[self::P1]);
        $this->assertSame([60], $f->game->discard);
        $this->assertSame(['P:70' => true], $f->game->used);
        $this->assertSame([], $f->proposal->plan(self::P1, StepMode::Main)->options, '使用済みは出ない');
    }

    public function testP070CanDiscardTheKeptDrawnCardAndRejectsCardsOutsideTheHand(): void
    {
        $f = new E3Fixture(0);
        $f->game->hand[self::P1] = [60];
        $f->satellite(self::P1, ['P070']);
        $plan = $f->proposal->plan(self::P1, StepMode::Main);

        $f->proposal->validate(self::P1, $plan, 1, [101, 102], [101], [], ['P:70' => 101]);
        $this->expectException(EffectViolation::class);
        $f->proposal->validate(self::P1, $plan, 1, [101, 102], [101], [], ['P:70' => 102]);
    }

    public function testP072BuysKeepSlotsUpToTheNumberDrawn(): void
    {
        $f = new E3Fixture(10);
        $f->satellite(self::P1, ['P072']);
        $plan = $f->proposal->plan(self::P1, StepMode::Bonus);
        $drawn = [101, 102, 103, 104, 105];

        $this->assertSame(2, $f->proposal->keepCount($plan, 2, 5));
        $this->assertSame(3, $f->proposal->keepCount($plan, 2, 5, ['P:72' => 4]));
        $this->assertSame(4, $f->proposal->keepCount($plan, 2, 5, ['P:72' => 7]));
        $this->assertSame(5, $f->proposal->keepCount($plan, 2, 5, ['P:72' => 10]), '5 枚引いて 5 枚まで');
        $this->assertSame(2, $f->proposal->keepCount($plan, 1, 2, ['P:72' => 10]), '引いた枚数を超えない');

        $f->proposal->validate(self::P1, $plan, 2, $drawn, [101, 102, 103, 104], [], ['P:72' => 7]);
        $f->proposal->apply(self::P1, $plan, [], ['P:72' => 7]);
        $this->assertSame(3, $f->game->maya[self::P1]);
        $this->assertSame(['P:72' => true], $f->game->used);
    }

    public function testP072ValidationRejectsWrongTiersWrongKeepCountsAndMissingMaya(): void
    {
        $f = new E3Fixture(6);
        $f->game->hand[self::P1] = [60, 61];
        $f->satellite(self::P1, ['P072']);
        $plan = $f->proposal->plan(self::P1, StepMode::Bonus);
        $drawn = [101, 102, 103, 104, 105];
        $bad = [
            'tier 5' => [[101, 102, 103], ['P:72' => 5], []],
            'keep count does not follow the purchase' => [[101, 102], ['P:72' => 4], []],
            'not enough maya' => [[101, 102, 103, 104], ['P:72' => 7], []],
            'unknown option' => [[101, 102], ['P:99' => 4], []],
        ];
        foreach ($bad as $name => [$keep, $uses, $sell]) {
            try {
                $f->proposal->validate(self::P1, $plan, 2, $drawn, $keep, [], $uses, $sell);
                $this->fail($name);
            } catch (EffectViolation) {
                $this->addToAssertionCount(1);
            }
        }
        // 売却を同梱すれば払える: 6 + 3 + 3 = 12 ≥ 7
        $f->proposal->validate(self::P1, $plan, 2, $drawn, [101, 102, 103, 104], [], ['P:72' => 7], [60, 61]);
        $f->proposal->apply(self::P1, $plan, [], ['P:72' => 7], [60, 61]);
        $this->assertSame(5, $f->game->maya[self::P1]);
        $this->assertSame([], $f->game->hand[self::P1]);
    }

    public function testP068LetsTheDiscardsComeFromHandWithoutChangingTheirNumber(): void
    {
        $f = new E3Fixture();
        $f->game->hand[self::P1] = [60, 61];
        $f->satellite(self::P1, ['P068']);
        $plan = $f->proposal->plan(self::P1, StepMode::Main);
        $this->assertTrue($plan->handDiscard);

        // 2 枚引いて 1 枚残す → 1 枚捨てる。手札の 60 を捨てれば、引いた 2 枚とも残せる
        $f->proposal->validate(self::P1, $plan, 1, [101, 102], [101, 102], [60], []);
        $f->proposal->apply(self::P1, $plan, [60], []);
        $this->assertSame([61], $f->game->hand[self::P1]);
        $this->assertSame(0, $f->game->maya[self::P1] - 20, '捨てても売却額は得ない');
        $this->assertSame([], $f->game->used, '常時の効果に使用済みはない');

        foreach ([[[101], [60]], [[101, 102], []], [[101, 102], [99]]] as [$keep, $handDiscard]) {
            try {
                $f->proposal->validate(self::P1, $plan, 1, [101, 102], $keep, $handDiscard, []);
                $this->fail('捨てる枚数は変わらない');
            } catch (EffectViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testHandDiscardNeedsP068AndDescribeShowsTheOptions(): void
    {
        $f = new E3Fixture(5);
        $f->game->hand[self::P1] = [60];
        $f->satellite(self::P1, ['P070', 'P072']);
        $plan = $f->proposal->plan(self::P1, StepMode::Main);

        try {
            $f->proposal->validate(self::P1, $plan, 1, [101, 102], [101, 102], [60], []);
            $this->fail('P068 がなければ手札からは捨てられない');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        $described = $f->proposal->describe(self::P1, $plan, [101, 102]);
        $this->assertFalse($described['handDiscard']);
        $this->assertSame(['EXTRA_DISCARD', 'KEEP_PURCHASE'], array_column($described['options'], 'kind'));
        $this->assertSame([4], $described['options'][1]['affordable']);
        $this->assertSame([4, 7], $described['options'][1]['affordableBySelling'], '手札 1 枚を売れば 5 + 3 = 8 マヤまで');
    }

    public function testProposalOptionsNeedAnActiveSatellite(): void
    {
        $f = new E3Fixture();
        $f->satellite(self::P1, ['P068', 'P070', 'P072'], false);

        $plan = $f->proposal->plan(self::P1, StepMode::Main);
        $this->assertFalse($plan->handDiscard);
        $this->assertSame([], $plan->options);
    }

    // ---- P086 データダウンリンク ----

    private function entered(int $sat, int $cell): GameEvent
    {
        return new GameEvent(Hook::OrbitEntered, self::P1, $sat, null, null, $cell, 0, ['fromRegion' => 'A', 'toRegion' => 'B']);
    }

    public function testP086PaysThreeMayaForOneCardWhenEnteringOwnLinkedArea(): void
    {
        $f = new E3Fixture(5);
        $sat = $f->satellite(self::P1, ['P086']);
        $f->game->linkedCells[self::P1] = [12];
        $engine = $f->engine();

        $engine->openWindow('O1', [$this->entered($sat, 12)]);
        $q = $this->ask($engine->questionsFor(self::P1), 'data_downlink');
        $engine->useEffect(self::P1, (int) $q->entryId, new Answer());

        $this->assertSame(2, $f->game->maya[self::P1]);
        $this->assertSame([101], $f->game->hand[self::P1]);
    }

    public function testP086DoesNotAskOutsideOwnLinkedAreasOrOtherSatellites(): void
    {
        $f = new E3Fixture(5);
        $sat = $f->satellite(self::P1, ['P086']);
        $other = $f->satellite(self::P1);
        $f->game->linkedCells[self::P1] = [12];

        $this->assertFalse($f->engine()->openWindow('O1', [$this->entered($sat, 13)])->needsInput());
        $this->assertFalse($f->engine()->openWindow('O1', [$this->entered($other, 12)])->needsInput());
        $this->assertSame(5, $f->game->maya[self::P1]);
    }

    // ---- P087 データローリング（2 段）----

    private function orbitStart(): GameEvent
    {
        return new GameEvent(Hook::OrbitStart, null);
    }

    public function testP087DrawsThenAsksAMandatoryDiscardOfOneHandCardWithoutSellingIt(): void
    {
        $f = new E3Fixture(0);
        $f->game->hand[self::P1] = [50];
        $f->game->sellPrice = 7;
        $f->satellite(self::P1, ['P087']);
        $engine = $f->engine();

        $report = $engine->openWindow('O0', [$this->orbitStart()]);

        $this->assertSame([self::P1], $report->playersNeedingInput);
        $this->assertSame([50, 101], $f->game->hand[self::P1], '1 段目は自動で引く');
        $q = $this->ask($engine->questionsFor(self::P1), 'data_rolling');
        $this->assertTrue($q->mandatory);
        $this->assertSame(['kind' => 'NONE'], $q->cost);
        $this->assertSame('cards', $q->fields[0]->key);
        $this->assertSame(FieldType::Cards, $q->fields[0]->type);
        $this->assertSame([50, 101], $q->fields[0]->candidates);
        $this->assertSame([1, 1], [$q->fields[0]->min, $q->fields[0]->max]);

        try {
            $engine->skipEffect(self::P1, (int) $q->entryId);
            $this->fail('強制の捨ては [Skip] できない');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        try {
            $engine->useEffect(self::P1, (int) $q->entryId, new Answer(['cards' => [999]]));
            $this->fail('手札にないカード');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(0, $f->game->maya[self::P1]);

        $engine->useEffect(self::P1, (int) $q->entryId, new Answer(['cards' => [50]]));

        $this->assertSame([101], $f->game->hand[self::P1]);
        $this->assertSame([50], $f->game->discard);
        $this->assertSame(0, $f->game->maya[self::P1], '効果による捨てなので売却額は得ない（FAQ 行 70）');
        $this->assertSame(['P:87' => true], $f->game->used);
        $this->assertFalse($engine->questionsFor(self::P1) !== []);
    }

    public function testP087ZombieDiscardsTheJustDrawnCardAndSurvivesASnapshot(): void
    {
        $f = new E3Fixture(0);
        $f->game->hand[self::P1] = [50, 40];
        $f->satellite(self::P1, ['P087']);
        $engine = $f->engine();
        $engine->openWindow('O0', [$this->orbitStart()]);

        $restored = $f->engine();
        $restored->restore($engine->snapshot());
        $restored->zombie(self::P1);

        $this->assertSame([50, 40], $f->game->hand[self::P1], '直前に引いた 101 を捨てて手札は増減しない');
        $this->assertSame([101], $f->game->discard);
        $this->assertSame(1, $restored->closeWindow()->resolvedCount);
    }

    public function testP087ZombieFallsBackToTheLowestCardIdWhenNothingWasDrawnAndDoesNothingWithAnEmptyHand(): void
    {
        $f = new E3Fixture(0);
        $f->game->deck = [];
        $f->game->hand[self::P1] = [50, 40];
        $f->satellite(self::P1, ['P087']);
        $engine = $f->engine();
        $engine->openWindow('O0', [$this->orbitStart()]);
        $engine->zombie(self::P1);
        $this->assertSame([50], $f->game->hand[self::P1]);

        $g = new E3Fixture(0);
        $g->game->deck = [];
        $g->satellite(self::P1, ['P087']);
        $empty = $g->engine();
        $report = $empty->openWindow('O0', [$this->orbitStart()]);
        $this->assertFalse($report->needsInput());
        $this->assertSame([], $g->game->discard);
    }

    // ---- EUROPA: R016〜R020 ----

    /** @return array<string, array{string, ActionKind}> */
    public static function europa(): array
    {
        return [
            'R016' => ['hammer_europa', ActionKind::Proposal],
            'R017' => ['retro_europa', ActionKind::Deploy],
            'R018' => ['scallop_europa', ActionKind::Link],
            'R019' => ['concorde_europa', ActionKind::LaunchTurn],
            'R020' => ['stealth_europa', ActionKind::Budget],
        ];
    }

    private function europaFixture(string $key, int $maya = 10): E3Fixture
    {
        $f = new E3Fixture($maya);
        $f->satellite(self::P1, ['P013', 'P034', 'P067'], true, $key);

        return $f;
    }

    #[DataProvider('europa')]
    public function testEuropaDeclaresTheBonusForThreeMayaWhenSomeoneElseChoseTheAction(string $key, ActionKind $action): void
    {
        $f = $this->europaFixture($key);
        $engine = $f->engine();

        $report = $engine->openWindow('XD', [BonusDeclaration::event($action, [self::P2])]);

        $this->assertSame([self::P1], $report->playersNeedingInput);
        $q = $this->ask($engine->questionsFor(self::P1), $key);
        $this->assertSame(['kind' => 'PAY_MAYA', 'amount' => 3, 'canPay' => true, 'shortfall' => 0, 'canPayBySelling' => true], $q->cost);
        $engine->useEffect(self::P1, (int) $q->entryId, new Answer());

        $this->assertSame(7, $f->game->maya[self::P1]);
        $this->assertTrue($f->game->declaredBonus(self::P1, $action->value));
        $this->assertSame([self::P1, self::P2], BonusDeclaration::holders($f->game, $action, [self::P2]));
        $this->assertCount(1, $f->game->used);
    }

    #[DataProvider('europa')]
    public function testT25EuropaCannotBeUsedWhenNobodyChoseTheActionOrTheWrongActionRuns(string $key, ActionKind $action): void
    {
        $f = $this->europaFixture($key);

        $this->assertFalse($f->engine()->openWindow('XD', [BonusDeclaration::event($action, [])])->needsInput(), '誰も選んでいない（FAQ 行 62）');
        $wrong = $action === ActionKind::Deploy ? ActionKind::Link : ActionKind::Deploy;
        $this->assertFalse($f->engine()->openWindow('XD', [BonusDeclaration::event($wrong, [self::P2])])->needsInput(), '別のアクションのフェイズ');
        $this->assertFalse($f->engine()->openWindow('XD', [BonusDeclaration::event($action, [self::P1, self::P2])])->needsInput(), '自分で選んでいればボーナスは得ている');
        $this->assertSame(10, $f->game->maya[self::P1]);
    }

    public function testEuropaNeedsCrewDeviceAndSoftwareOnItsSatelliteAndMaya(): void
    {
        $f = new E3Fixture(10);
        $f->satellite(self::P1, ['P013', 'P034'], true, 'hammer_europa');
        $this->assertFalse($f->engine()->openWindow('XD', [BonusDeclaration::event(ActionKind::Proposal, [self::P2])])->needsInput(), 'SOFTWARE がない');

        $g = $this->europaFixture('hammer_europa', 2);
        $engine = $g->engine();
        $engine->openWindow('XD', [BonusDeclaration::event(ActionKind::Proposal, [self::P2])]);
        $q = $this->ask($engine->questionsFor(self::P1), 'hammer_europa');
        $this->assertFalse($q->cost['canPay']);
        $this->expectException(EffectViolation::class);
        $engine->useEffect(self::P1, (int) $q->entryId, new Answer());
    }

    public function testEuropaSkipDeclaresNothing(): void
    {
        $f = $this->europaFixture('hammer_europa');
        $engine = $f->engine();
        $engine->openWindow('XD', [BonusDeclaration::event(ActionKind::Proposal, [self::P2])]);
        $engine->skipEffect(self::P1, $this->entryOf($engine, self::P1, 'hammer_europa'));

        $this->assertFalse($f->game->declaredBonus(self::P1, 'PROPOSAL'));
        $this->assertSame([], $f->game->used);
        $this->assertSame([self::P2], BonusDeclaration::holders($f->game, ActionKind::Proposal, [self::P2]));
    }

    public function testEuropaZombieSkipsAndPaysNothing(): void
    {
        $f = $this->europaFixture('hammer_europa');
        $engine = $f->engine();
        $engine->openWindow('XD', [BonusDeclaration::event(ActionKind::Proposal, [self::P2])]);
        $engine->zombie(self::P1);

        $this->assertSame(10, $f->game->maya[self::P1]);
        $this->assertFalse($engine->playersNeedingInput() !== []);
    }

    public function testT24TheDeclaredBonusGetsDeimosAndP007ButNotR011(): void
    {
        // R011（HAMMER DEIMOS）は自分で企画を選んだときに限る。宣言（R016）で得たボーナスには効かない（FAQ 行 71）
        $f = new E3Fixture();
        $f->satellite(self::P1, [], true, 'hammer_deimos');
        $base = new ProposalCounts(5, 2);
        $own = $f->hooks->proposalCounts(self::P1, true, $base);
        $this->assertSame([6, 3], [$own->draw, $own->keep]);

        $f->game->declareBonus(self::P1, 'PROPOSAL');
        $declared = $f->hooks->proposalCounts(self::P1, true, $base);
        $this->assertSame([5, 2], [$declared->draw, $declared->keep]);

        // 作者回答: R012〜R015 は宣言で得たボーナスにも適用する。B2 の保持者に宣言した者を含めれば P007・R015 が働く
        $g = new E3Fixture(0);
        $g->satellite(self::P1, ['P007'], true, 'stealth_europa');
        $g->satellite(self::P2, [], true, 'stealth_deimos');
        $g->game->declareBonus(self::P1, 'BUDGET');
        $g->game->declareBonus(self::P2, 'BUDGET');
        $holders = BonusDeclaration::holders($g->game, ActionKind::Budget, []);
        $g->engine()->openWindow('B2', [new GameEvent(Hook::BudgetBonusGain, null, null, null, null, null, 0, ['holders' => $holders])]);
        $this->assertSame(2, $g->game->maya[self::P1], 'P007');
        $this->assertSame(3, $g->game->maya[self::P2], 'R015');
    }

    // ---- 問いの組み立てと答えの検証（011 §6）----

    public function testQuestionsSerializeToTheArgsShape(): void
    {
        $f = new E3Fixture(10);
        $f->game->hand[self::P1] = [50];
        $f->satellite(self::P1, ['P005', 'P087']);
        $engine = $f->engine();
        $engine->openWindow('B3', [$this->budgetFree()]);

        $effect = $engine->questionsFor(self::P1)[0]->toArray();
        $this->assertSame('EFFECT', $effect['type']);
        $this->assertSame('procurement_officer', $effect['cardKey']);
        $this->assertFalse($effect['mandatory']);
        $this->assertSame('PAY_MAYA', $effect['cost']['kind']);
        $this->assertSame([], $effect['fields']);
        $this->assertSame([['entryId' => $effect['entryId'], 'cardKey' => 'procurement_officer']], $engine->pendingFor(self::P1));

        $g = new E3Fixture(0);
        $g->game->hand[self::P1] = [50];
        $g->satellite(self::P1, ['P087']);
        $e = $g->engine();
        $e->openWindow('O0', [$this->orbitStart()]);
        $discard = $e->questionsFor(self::P1)[0]->toArray();
        $this->assertTrue($discard['mandatory']);
        $this->assertSame(['key' => 'cards', 'type' => 'CARDS', 'min' => 1, 'max' => 1, 'candidates' => [50, 101], 'default' => null], $discard['fields'][0]);
    }

    public function testParallelQuestionsAreAskedForIndependentOptionalEffectsAndAnswersDoNotChangeTheOthers(): void
    {
        // P005（M-, H+）と P087 は別の窓。同じ窓の並列は P069 と P086 のような独立した効果で見る代わりに、2 人のプールで確かめる（S09）
        $f = new E3Fixture(10);
        $f->satellite(self::P1, ['P005']);
        $f->satellite(self::P2, ['P005']);
        $engine = $f->engine();
        $report = $engine->openWindow('B3', [$this->budgetFree()]);

        $this->assertSame([self::P1, self::P2], $report->playersNeedingInput);
        $engine->useEffect(self::P1, $this->entryOf($engine, self::P1, 'procurement_officer'), new Answer());
        $this->assertSame([self::P2], $engine->playersNeedingInput());
        $q = $this->ask($engine->questionsFor(self::P2), 'procurement_officer');
        $this->assertTrue($q->cost['canPay'], 'P1 の答えは P2 の問いを変えない');
        $this->assertSame(10, $f->game->maya[self::P2]);
    }

    public function testNothingCanBeAnsweredTwiceOrSkippedWhenAnotherEffectMustComeFirst(): void
    {
        $f = new E3Fixture(10);
        $f->satellite(self::P1, ['P006'], true, 'stealth_phobos');
        $engine = $f->engine();
        $engine->openWindow('B0', [new GameEvent(Hook::BudgetStart, null)]);
        $ids = array_column($engine->questionsFor(self::P1)[0]->entries, 'entryId', 'cardKey');

        try {
            $engine->useEffect(self::P1, $ids['budget_negotiator'], new Answer());
            $this->fail('順序を決める前は答えられない');
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);
        }
        $engine->orderEffects(self::P1, [$ids['budget_negotiator'], $ids['stealth_phobos']]);
        $engine->useEffect(self::P1, $ids['budget_negotiator'], new Answer());
        $this->expectException(EffectViolation::class);
        $engine->useEffect(self::P1, $ids['budget_negotiator'], new Answer());
    }

    public function testASellBundledWithAnAnswerThatNeedsNoPaymentIsRejected(): void
    {
        $f = new E3Fixture(0);
        $f->game->hand[self::P1] = [50];
        $f->satellite(self::P1, ['P087']);
        $engine = $f->engine();
        $engine->openWindow('O0', [$this->orbitStart()]);
        $q = $this->ask($engine->questionsFor(self::P1), 'data_rolling');

        $this->expectException(EffectViolation::class);
        $engine->useEffect(self::P1, (int) $q->entryId, new Answer(['cards' => [50]], [50]));
    }
}
