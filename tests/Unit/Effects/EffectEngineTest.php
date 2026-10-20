<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects;

use Bga\Games\projectedgeworth\Effects\Context\GameEvent;
use Bga\Games\projectedgeworth\Effects\Context\StagedLinkRequest;
use Bga\Games\projectedgeworth\Effects\EffectViolation;
use Bga\Games\projectedgeworth\Effects\Outcome;
use Bga\Games\projectedgeworth\Effects\Parts\PartCall;
use Bga\Games\projectedgeworth\Effects\Parts\PartRegistry;
use Bga\Games\projectedgeworth\Effects\Question\Answer;
use Bga\Games\projectedgeworth\Effects\Question\Field;
use Bga\Games\projectedgeworth\Effects\Question\FieldType;
use Bga\Games\projectedgeworth\Material\CardEffects;
use Bga\Games\projectedgeworth\Material\EffectKind;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\PartId;
use Bga\Games\projectedgeworth\Material\ProjectType;
use Bga\Games\projectedgeworth\Material\ResourceSymbol;
use Bga\Games\projectedgeworth\Material\ResourceTouch;
use Bga\Games\projectedgeworth\Material\SatelliteShape;
use ProjectEdgeworthTests\Support\Effects\ArrayCatalog;
use ProjectEdgeworthTests\Support\Effects\FakeGame;
use ProjectEdgeworthTests\Support\Effects\ScriptedResultPart;
use ProjectEdgeworthTests\Support\Effects\TestSpecs;

/**
 * 効果エンジン（窓・プール・問い・答え・連鎖・使用済み・安全弁・zombie）。011 §11-4 の S01〜S10 のうちエンジンで検証できるもの。
 */
final class EffectEngineTest extends EffectsTestCase
{
    private function budgetGame(int $maya, array $hand = []): FakeGame
    {
        $game = (new FakeGame())->player(1, $maya, $hand);
        $sat = $game->satellite(1, SatelliteShape::Stealth, true, 'stealth_phobos');
        $game->deploy($sat, 'P006');

        return $game;
    }

    private function budgetStart(): GameEvent
    {
        return new GameEvent(Hook::BudgetStart, 1);
    }

    // ---- S01・S06・S10: 予算発行の開始時（P006 × R010） ----

    public function testS01AskingOrderThenResolvingInTheChosenOrder(): void
    {
        $game = $this->budgetGame(3);
        $engine = $this->engine($game);

        $report = $engine->openWindow('B0', [$this->budgetStart()]);

        $this->assertTrue($report->needsInput());
        $this->assertFalse($report->canClose());
        $this->assertSame([1], $report->playersNeedingInput);
        $questions = $engine->questionsFor(1);
        $this->assertSame(['ORDER'], $this->types($questions));
        $ids = $this->entryIds($questions);
        // デフォルトの順: 衛星カード（R010）→ 配備したカード（P006）。強制の効果も集合に含める
        $this->assertSame([$ids['stealth_phobos'], $ids['budget_negotiator']], $questions[0]->defaultOrder);
        $this->assertSame(
            [true, false],
            array_map(static fn(array $e): bool => $e['forced'], $questions[0]->entries),
        );

        $engine->orderEffects(1, [$ids['stealth_phobos'], $ids['budget_negotiator']]);

        // R010 が自動で解決して 5 マヤになり、P006 の [Use] / [Skip] だけを聞く
        $this->assertSame(5, $game->maya[1]);
        $questions = $engine->questionsFor(1);
        $this->assertSame(['EFFECT'], $this->types($questions));
        $this->assertTrue($questions[0]->cost['canPay']);
        $this->assertFalse($questions[0]->mandatory);

        $engine->useEffect(1, $ids['budget_negotiator'], new Answer());

        $this->assertSame(0, $game->maya[1]);
        $this->assertSame(1, $game->budget[1]);
        $this->assertEquals(['P:6' => true, 'S:1' => true], $game->used);
        $this->assertTrue($engine->closeWindow()->resolvedCount === 2);
    }

    public function testS01WithR010FirstAShortPlayerCanStillUseP006ButNotTheOtherWay(): void
    {
        $game = $this->budgetGame(3);
        $engine = $this->engine($game);
        $engine->openWindow('B0', [$this->budgetStart()]);
        $ids = $this->entryIds($engine->questionsFor(1));

        // P006 を先にすると 3 マヤでは払えない（売る手札もない）
        $engine->orderEffects(1, [$ids['budget_negotiator'], $ids['stealth_phobos']]);
        $question = $engine->questionsFor(1)[0];
        $this->assertFalse($question->cost['canPay']);
        $this->assertFalse($question->cost['canPayBySelling']);
        $this->assertSame(2, $question->cost['shortfall']);
        try {
            $engine->useEffect(1, $ids['budget_negotiator'], new Answer());
            $this->fail('payment must be refused');
        } catch (EffectViolation) {
            $this->assertSame(3, $game->maya[1]);
        }

        // [Skip] すると R010 が解決される。P006 は使用済みにならない
        $engine->skipEffect(1, $ids['budget_negotiator']);
        $this->assertSame(5, $game->maya[1]);
        $this->assertArrayNotHasKey('P:6', $game->used);
        $this->assertTrue($engine->barrier()->canClose());
    }

    public function testS06SinglePoolEntryAsksUseOrSkipWithoutOrder(): void
    {
        $game = (new FakeGame())->player(1, 10);
        $sat = $game->satellite(1, SatelliteShape::Hammer);
        $game->deploy($sat, 'P006');
        $engine = $this->engine($game);

        $engine->openWindow('B0', [$this->budgetStart()]);

        $this->assertSame(['EFFECT'], $this->types($engine->questionsFor(1)));
        $this->assertCount(1, $engine->pendingFor(1));
    }

    public function testSkippingDoesNotMarkUsedAndALaterTriggerAsksAgain(): void
    {
        $game = (new FakeGame())->player(1, 10);
        $sat = $game->satellite(1, SatelliteShape::Hammer);
        $game->deploy($sat, 'P006');
        $engine = $this->engine($game);
        $engine->openWindow('B0', [$this->budgetStart()]);
        $engine->skipEffect(1, $engine->questionsFor(1)[0]->entryId ?? 0);

        $this->assertSame([], $game->used);
        $this->assertSame([], $engine->questionsFor(1));

        // 同じ窓で再びトリガーされたら、もう一度聞く（007 §3-1）
        $engine->collect($this->budgetStart());
        $this->assertSame(['EFFECT'], $this->types($engine->questionsFor(1)));
    }

    public function testS10ZombieUsesDefaultOrderSkipsOptionalAndEmptiesThePool(): void
    {
        $game = $this->budgetGame(3);
        $engine = $this->engine($game);
        $engine->openWindow('B0', [$this->budgetStart()]);

        $engine->zombie(1);

        // R010（強制）は通常どおり +2 マヤ、P006（任意）は [Skip]
        $this->assertSame(5, $game->maya[1]);
        $this->assertSame(0, $game->budget[1]);
        $this->assertSame(['S:1' => true], $game->used);
        $this->assertSame([], $engine->questionsFor(1));
        $this->assertTrue($engine->barrier()->canClose());
    }

    public function testSellingCardsInTheUseAnswerCoversThePayment(): void
    {
        $game = (new FakeGame())->player(1, 1, [1, 2]);
        $sat = $game->satellite(1, SatelliteShape::Hammer);
        $game->deploy($sat, 'P006');
        $engine = $this->engine($game);
        $engine->openWindow('B0', [$this->budgetStart()]);
        $question = $engine->questionsFor(1)[0];
        $this->assertFalse($question->cost['canPay']);
        $this->assertTrue($question->cost['canPayBySelling']);
        $entry = (int) $question->entryId;

        foreach ([[99], [1, 1], [1]] as $bad) {
            try {
                $engine->useEffect(1, $entry, new Answer([], $bad));
                $this->fail('invalid sale must be refused');
            } catch (EffectViolation) {
                $this->assertSame(1, $game->maya[1]);
                $this->assertSame([1, 2], $game->hand[1]);
            }
        }

        $engine->useEffect(1, $entry, new Answer([], [1, 2]));

        // 1 + 3 + 3 - 5 = 2
        $this->assertSame(2, $game->maya[1]);
        $this->assertSame([], $game->hand[1]);
        $this->assertSame(1, $game->budget[1]);
    }

    // ---- S02・S03・S04: 配備・打ち上げの窓 ----

    private function deployGame(string ...$cards): array
    {
        $game = (new FakeGame())->player(1, 0, [1]);
        $sat = $game->satellite(1, SatelliteShape::Hammer);
        foreach ($cards as $card) {
            $game->deploy($sat, $card);
        }

        return [$game, $sat];
    }

    public function testS02DrawingFirstMakesTheDrawnDeviceDeployable(): void
    {
        [$game, $sat] = $this->deployGame('P011', 'P023');
        $game->deck = [40];
        $engine = $this->engine($game);
        $engine->openWindow('DE', [$this->deployed(1, $sat, 34, ProjectType::Device)]);
        $questions = $engine->questionsFor(1);
        $this->assertSame(['ORDER'], $this->types($questions));
        $ids = $this->entryIds($questions);

        $engine->orderEffects(1, [$ids['integrated_planner'], $ids['twin_deploy_technician']]);

        $this->assertSame([1, 40], $game->hand[1]);
        $question = $engine->questionsFor(1)[0];
        $this->assertSame([40], $question->fields[0]->candidates);
        $engine->useEffect(1, $ids['twin_deploy_technician'], new Answer(['card' => [40]]));
        $this->assertSame([1], $game->hand[1]);
        $this->assertSame(['draw', 'deployFromHand'], $game->journalNames());
        $this->assertEquals(['P:23' => true, 'P:11' => true], $game->used);
    }

    public function testS02DeployingFirstFindsNothingToDeployAndDropsTheEffect(): void
    {
        [$game, $sat] = $this->deployGame('P011', 'P023');
        $game->deck = [40];
        $engine = $this->engine($game);
        $engine->openWindow('DE', [$this->deployed(1, $sat, 34, ProjectType::Device)]);
        $ids = $this->entryIds($engine->questionsFor(1));

        $engine->orderEffects(1, [$ids['twin_deploy_technician'], $ids['integrated_planner']]);

        // P011 の番では配備できる DEVICE が手札にないので使えず、使用済みにもならない
        $this->assertSame(['draw'], $game->journalNames());
        $this->assertArrayNotHasKey('P:11', $game->used);
        $this->assertSame([], $engine->questionsFor(1));
    }

    public function testS03NonConflictingEffectsResolveAutomaticallyInDefaultOrder(): void
    {
        [$game, $sat] = $this->deployGame('P013', 'P023');
        $game->deck = [40];
        $engine = $this->engine($game);

        $report = $engine->openWindow('DE', [$this->deployed(1, $sat, 1, ProjectType::Crew)]);

        $this->assertFalse($report->needsInput());
        $this->assertTrue($report->canClose());
        $this->assertSame(['budget', 'draw'], $game->journalNames());
        $this->assertSame(1, $game->budget[1]);
        $this->assertSame([1, 40], $game->hand[1]);
        $logs = $engine->closeWindow()->logs;
        $this->assertSame(['effectUsed', 'effectUsed'], array_map(static fn($l): string => $l->key, $logs));
    }

    public function testS04AccelerationBoosterAndFuelTransferPipeAskForOrder(): void
    {
        [$game, $sat] = $this->deployGame('P034', 'P038');
        $engine = $this->engine($game);
        $engine->openWindow('LE', [new GameEvent(Hook::Launched, 1, $sat)]);
        $questions = $engine->questionsFor(1);
        $this->assertSame(['ORDER'], $this->types($questions));
        $ids = $this->entryIds($questions);

        $engine->orderEffects(1, [$ids['fuel_transfer_pipe'], $ids['acceleration_booster']]);
        $this->assertSame(['EFFECT'], $this->types($engine->questionsFor(1)));
        $engine->skipEffect(1, $ids['fuel_transfer_pipe']);
        $question = $engine->questionsFor(1)[0];
        $this->assertSame('acceleration_booster', $question->cardKey);
        $this->assertSame('PAY_MAYA_TIER', $question->cost['kind']);

        try {
            $engine->useEffect(1, $ids['acceleration_booster'], new Answer(['areas' => 9]));
            $this->fail('areas outside the candidates must be refused');
        } catch (EffectViolation) {
            $this->assertSame([], $game->journal);
        }
        $engine->useEffect(1, $ids['acceleration_booster'], new Answer(['areas' => 2]));

        $this->assertSame([['move', 2]], $game->journal);
        $this->assertArrayNotHasKey('P:38', $game->used);
        $this->assertTrue($engine->barrier()->canClose());
    }

    // ---- S09: 全員の並行 ----

    public function testS09OnePlayersAnswerDoesNotChangeAnotherPlayersQuestions(): void
    {
        $game = (new FakeGame())->player(1, 10)->player(2, 10);
        $game->deploy($game->satellite(1, SatelliteShape::Hammer), 'P006');
        $game->satellite(2, SatelliteShape::Hammer, true, 'optional_gain');
        $catalog = new ArrayCatalog([
            CardEffects::get('budget_negotiator') ?? throw new \LogicException('sample'),
            TestSpecs::spec('optional_gain', Hook::BudgetStart, optional: true, params: ['amount' => 3]),
        ]);
        $engine = $this->engine($game, null, $catalog);

        $report = $engine->openWindow('B0', [new GameEvent(Hook::BudgetStart, null)]);

        $this->assertSame([1, 2], $report->playersNeedingInput);
        $firstId = (int) $engine->questionsFor(1)[0]->entryId;
        $secondBefore = array_map(static fn($q): array => $q->toArray(), $engine->questionsFor(2));
        $engine->useEffect(1, $firstId, new Answer());

        $this->assertSame($secondBefore, array_map(static fn($q): array => $q->toArray(), $engine->questionsFor(2)));
        $this->assertSame(5, $game->maya[1]);
        $this->assertSame(10, $game->maya[2]);
        $this->assertSame([2], $engine->barrier()->playersNeedingInput);
    }

    // ---- 使用済み・トリガーの収集 ----

    public function testAnActivatedEffectTriggeredTwiceResolvesOnlyOnce(): void
    {
        [$game, $sat] = $this->deployGame('P013');
        $engine = $this->engine($game);

        $engine->openWindow('DE', [
            $this->deployed(1, $sat, 1, ProjectType::Crew),
            $this->deployed(1, $sat, 2, ProjectType::Crew),
        ]);

        $this->assertSame(1, $game->budget[1]);
        // 窓を閉じた後の別の窓でも、使用済みなので積まれない（ラウンドのリセットは 20 RoundStart）
        $engine->closeWindow();
        $report = $engine->openWindow('DE', [$this->deployed(1, $sat, 3, ProjectType::Crew)]);
        $this->assertFalse($report->hadEffects);
        $this->assertSame(1, $game->budget[1]);
    }

    public function testReactiveEffectsIgnoreTheirOwnDeploymentAndInactiveHosts(): void
    {
        [$game, $sat] = $this->deployGame('P013', 'P023');
        $engine = $this->engine($game);

        // 自身の配備には反応しない（FAQ 行 11）
        $this->assertFalse($engine->openWindow('DE', [$this->deployed(1, $sat, 13, ProjectType::Crew)])->hadEffects
            && $game->budget[1] > 0);
        $engine->closeWindow();

        // 稼働していない衛星では ACTIVE_ONLY の P013 は働かず、稼働条件なしの P023 は働く
        $game->setActive($sat, false);
        $game->deck = [40];
        $game->used = [];
        $game->budget[1] = 0;
        $engine->openWindow('DE', [$this->deployed(1, $sat, 1, ProjectType::Crew)]);
        $this->assertSame(0, $game->budget[1]);
        $this->assertSame([1, 40], $game->hand[1]);
    }

    public function testSelfOnlyEffectsReactOnlyToTheirOwnSatelliteBeingLaunched(): void
    {
        $game = (new FakeGame())->player(1, 0, []);
        $a = $game->satellite(1, SatelliteShape::Hammer);
        $b = $game->satellite(1, SatelliteShape::Retro);
        $game->deploy($a, 'P039')->deploy($a, 'P040');
        $game->deck = [1, 2, 3];
        $engine = $this->engine($game);

        $report = $engine->openWindow('LE', [new GameEvent(Hook::Launched, 1, $b)]);
        $this->assertFalse($report->hadEffects);
        $engine->closeWindow();

        $engine->openWindow('LE', [new GameEvent(Hook::Launched, 1, $a)]);
        // DEVICE が 2 枚（P039 自身と P040）なので 2 枚引く
        $this->assertSame([1, 2], $game->hand[1]);
    }

    public function testAnEmptyWindowCanBeClosedAtOnce(): void
    {
        $game = (new FakeGame())->player(1);
        $game->satellite(1, SatelliteShape::Hammer);
        $engine = $this->engine($game);

        $report = $engine->openWindow('O0', [new GameEvent(Hook::OrbitStart, null)]);

        $this->assertFalse($report->hadEffects);
        $this->assertTrue($report->canClose());
        $this->assertSame(0, $engine->closeWindow()->resolvedCount);
        $this->assertFalse($engine->isOpen());
    }

    public function testAWindowWithAnswersPendingCannotBeClosed(): void
    {
        $game = $this->budgetGame(3);
        $engine = $this->engine($game);
        $engine->openWindow('B0', [$this->budgetStart()]);

        $this->expectException(\LogicException::class);
        $engine->closeWindow();
    }

    // ---- 答えの検証 ----

    public function testInvalidAnswersAreRefused(): void
    {
        [$game, $sat] = $this->deployGame('P011', 'P023');
        $game->deck = [40];
        $engine = $this->engine($game);
        $engine->openWindow('DE', [$this->deployed(1, $sat, 34, ProjectType::Device)]);
        $ids = $this->entryIds($engine->questionsFor(1));

        // 順序が決まるまでは、どの効果にも答えられない
        $this->expectViolation(fn() => $engine->useEffect(1, $ids['integrated_planner'], new Answer()));
        $this->expectViolation(fn() => $engine->skipEffect(1, $ids['twin_deploy_technician']));
        // 集合の全要素を 1 回ずつ並べていない順序は受け付けない
        $this->expectViolation(fn() => $engine->orderEffects(1, [$ids['integrated_planner']]));
        $this->expectViolation(fn() => $engine->orderEffects(1, [$ids['integrated_planner'], $ids['integrated_planner']]));
        $this->expectViolation(fn() => $engine->orderEffects(1, [$ids['integrated_planner'], 999]));

        $engine->orderEffects(1, [$ids['integrated_planner'], $ids['twin_deploy_technician']]);

        // 確定した順序は変えられない。いま答えられるのは先頭の P011 だけ
        $this->expectViolation(fn() => $engine->orderEffects(1, [$ids['twin_deploy_technician'], $ids['integrated_planner']]));
        $this->expectViolation(fn() => $engine->useEffect(1, 999, new Answer(['card' => [40]])));
        $this->expectViolation(fn() => $engine->useEffect(1, $ids['twin_deploy_technician'], new Answer(['card' => [1]])));
        $this->expectViolation(fn() => $engine->useEffect(1, $ids['twin_deploy_technician'], new Answer()));
        $this->expectViolation(fn() => $engine->useEffect(1, $ids['twin_deploy_technician'], new Answer(['card' => [40], 'x' => 1])));
        $this->expectViolation(fn() => $engine->useEffect(1, $ids['twin_deploy_technician'], new Answer(['card' => [40]], [1])));
        $this->assertSame([1, 40], $game->hand[1], '拒否された答えは何も変えない');
    }

    private function expectViolation(\Closure $call): void
    {
        try {
            $call();
        } catch (EffectViolation) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('EffectViolation was expected');
    }

    // ---- S05: 連鎖で未解決の効果と衝突する効果が加わる ----

    /**
     * @return array{FakeGame, \Bga\Games\projectedgeworth\Effects\EffectEngine, \ArrayObject<int, string>}
     */
    private function chainEngine(ResourceSymbol $thirdTouches): array
    {
        $game = (new FakeGame())->player(1);
        $game->satellite(1, SatelliteShape::Hammer, true, 'card_a');
        $game->satellite(1, SatelliteShape::Retro, true, 'card_b');
        $game->satellite(1, SatelliteShape::Scallop, true, 'card_c');
        $catalog = new ArrayCatalog([
            TestSpecs::spec('card_a', Hook::Launched, PartId::GainMaya, touches: [new ResourceTouch(ResourceSymbol::MayaPay)]),
            TestSpecs::spec('card_b', Hook::Launched, PartId::ChangeBudget, touches: [new ResourceTouch(ResourceSymbol::MayaGain)]),
            TestSpecs::spec('card_c', Hook::Turned, PartId::DrawCards, touches: [new ResourceTouch($thirdTouches)]),
        ]);
        $log = new \ArrayObject();
        $record = static fn(string $name, ?GameEvent $emit = null): \Closure => static function () use ($log, $name, $emit): Outcome {
            $log[] = $name;

            return new Outcome($emit === null ? [] : [$emit]);
        };
        $parts = (new PartRegistry())
            ->register(PartId::GainMaya, new ScriptedResultPart($record('A', new GameEvent(Hook::Turned, 1, 1))))
            ->register(PartId::ChangeBudget, new ScriptedResultPart($record('B')))
            ->register(PartId::DrawCards, new ScriptedResultPart($record('C')));
        $engine = $this->engine($game, $parts, $catalog);
        $engine->openWindow('LE', [new GameEvent(Hook::Launched, 1, 1)]);

        return [$game, $engine, $log];
    }

    public function testS05AChainedEffectThatConflictsWithAnUnresolvedOneAsksForTheOrderAgain(): void
    {
        [, $engine, $log] = $this->chainEngine(ResourceSymbol::MayaPay);
        $questions = $engine->questionsFor(1);
        $this->assertSame(['ORDER'], $this->types($questions));
        $ids = $this->entryIds($questions);

        $engine->orderEffects(1, [$ids['card_a'], $ids['card_b']]);

        // A の解決で C が加わり、C は未解決の B と衝突する → 残り（B）と新しい効果（C）で聞き直す。初期の並びは前回の相対順の後ろに C
        $questions = $engine->questionsFor(1);
        $this->assertSame(['ORDER'], $this->types($questions));
        $this->assertSame(['card_b', 'card_c'], array_map(static fn(array $e): string => $e['cardKey'], $questions[0]->entries));
        $this->assertSame(['A'], $log->getArrayCopy());
        $ids = $this->entryIds($questions);
        $engine->orderEffects(1, [$ids['card_c'], $ids['card_b']]);
        $this->assertSame(['A', 'C', 'B'], $log->getArrayCopy());
        $this->assertTrue($engine->barrier()->canClose());
    }

    public function testS05AChainedEffectThatDoesNotConflictIsNotAsked(): void
    {
        [, $engine, $log] = $this->chainEngine(ResourceSymbol::BudgetGain);
        $ids = $this->entryIds($engine->questionsFor(1));

        $engine->orderEffects(1, [$ids['card_a'], $ids['card_b']]);

        // 衝突しないので聞かずに、デフォルトの順（B → C）で自動に解決される
        $this->assertSame(['A', 'B', 'C'], $log->getArrayCopy());
        $this->assertSame([], $engine->questionsFor(1));
    }

    // ---- 安全弁 ----

    public function testTheWindowIsForceClosedAfterTwoHundredEffects(): void
    {
        $game = (new FakeGame())->player(1);
        $game->satellite(1, SatelliteShape::Hammer, true, 'loop');
        $catalog = new ArrayCatalog([TestSpecs::spec('loop', Hook::Landed)]);
        $parts = (new PartRegistry())->register(PartId::GainMaya, new ScriptedResultPart(
            static fn(): Outcome => new Outcome([new GameEvent(Hook::Landed, 1, 1)]),
        ));
        $engine = $this->engine($game, $parts, $catalog);

        $report = $engine->openWindow('O4', [new GameEvent(Hook::Landed, 1, 1)]);

        $this->assertTrue($report->aborted);
        $this->assertTrue($report->canClose());
        $result = $engine->closeWindow();
        $this->assertSame(200, $result->resolvedCount);
        $this->assertTrue($result->aborted);
        $this->assertSame('windowLimitReached', $result->logs[array_key_last($result->logs)]->key);
    }

    // ---- 多段・必須の問い・保存と復元・zombie ----

    /**
     * 2 段の効果（P087 に似せた疑似カード）: 1 段目は強制で自動（引く）、2 段目は強制の手札の選択（捨てる）。
     *
     * @return array{FakeGame, \Bga\Games\projectedgeworth\Effects\EffectEngine}
     */
    private function rollingEngine(): array
    {
        $game = (new FakeGame())->player(1, 0, [5]);
        $game->satellite(1, SatelliteShape::Hammer, true, 'rolling');
        $game->deck = [7];
        $catalog = new ArrayCatalog([TestSpecs::spec('rolling', Hook::OrbitStart, PartId::DrawCards, EffectKind::Activated, stages: 2)]);
        $parts = (new PartRegistry())->register(PartId::DrawCards, new ScriptedResultPart(
            static function (PartCall $c, ?Answer $a) use ($game): Outcome {
                if ($c->stage === 1) {
                    $game->draw(1, 1);
                } else {
                    $game->removeFromHand(1, (int) ($a?->fields['discard'][0] ?? 0));
                }

                return Outcome::none();
            },
            static fn(PartCall $c): array => $c->stage === 2 ? [new Field('discard', FieldType::Cards, $c->ctx->view->hand(1), 1, 1)] : [],
            onDefault: static fn(PartCall $c): Answer => new Answer(['discard' => [7]]),
            stageCount: 2,
        ));

        return [$game, $this->engine($game, $parts, $catalog)];
    }

    public function testAMultiStageEffectAsksAMandatoryQuestionAndIsUsedOnlyWhenFinished(): void
    {
        [$game, $engine] = $this->rollingEngine();
        $report = $engine->openWindow('O0', [new GameEvent(Hook::OrbitStart, null)]);

        $this->assertSame([1], $report->playersNeedingInput);
        $this->assertSame([5, 7], $game->hand[1]);
        $question = $engine->questionsFor(1)[0];
        $this->assertTrue($question->mandatory);
        $this->assertSame([5, 7], $question->fields[0]->candidates);
        $this->assertSame([], $game->used, '2 段目が終わるまで使用済みにならない');
        $this->expectViolation(fn() => $engine->skipEffect(1, (int) $question->entryId));

        // 再読み込み: 窓の状態を JSON にして別のエンジンに復元しても、途中の段から続けられる
        $snapshot = json_decode((string) json_encode($engine->snapshot()), true);
        $restored = $this->engine($game, $this->rollingEngineParts($game), new ArrayCatalog([
            TestSpecs::spec('rolling', Hook::OrbitStart, PartId::DrawCards, EffectKind::Activated, stages: 2),
        ]));
        $restored->restore($snapshot);
        $this->assertEquals($question->toArray(), $restored->questionsFor(1)[0]->toArray());

        $restored->useEffect(1, (int) $question->entryId, new Answer(['discard' => [5]]));

        $this->assertSame([7], $game->hand[1]);
        $this->assertSame(['S:1' => true], $game->used);
        $this->assertTrue($restored->barrier()->canClose());
    }

    private function rollingEngineParts(FakeGame $game): PartRegistry
    {
        return (new PartRegistry())->register(PartId::DrawCards, new ScriptedResultPart(
            static function (PartCall $c, ?Answer $a) use ($game): Outcome {
                $c->stage === 1 ? $game->draw(1, 1) : $game->removeFromHand(1, (int) ($a?->fields['discard'][0] ?? 0));

                return Outcome::none();
            },
            static fn(PartCall $c): array => $c->stage === 2 ? [new Field('discard', FieldType::Cards, $c->ctx->view->hand(1), 1, 1)] : [],
            stageCount: 2,
        ));
    }

    public function testZombieAnswersAMandatoryQuestionWithThePartsDefault(): void
    {
        [$game, $engine] = $this->rollingEngine();
        $engine->openWindow('O0', [new GameEvent(Hook::OrbitStart, null)]);

        $engine->zombie(1);

        // 直前に引いたカード（7）を捨てる
        $this->assertSame([5], $game->hand[1]);
        $this->assertSame(['S:1' => true], $game->used);
        $this->assertSame([], $engine->questionsFor(1));
    }

    public function testZombieDropsAMandatoryEffectWithoutADefaultAnswer(): void
    {
        $game = (new FakeGame())->player(1, 0, [5]);
        $game->satellite(1, SatelliteShape::Hammer, true, 'choosy');
        $catalog = new ArrayCatalog([TestSpecs::spec('choosy', Hook::OrbitStart, PartId::DrawCards)]);
        $parts = (new PartRegistry())->register(PartId::DrawCards, new ScriptedResultPart(
            onFields: static fn(PartCall $c): array => [new Field('card', FieldType::Cards, [5], 1, 1)],
        ));
        $engine = $this->engine($game, $parts, $catalog);
        $engine->openWindow('O0', [new GameEvent(Hook::OrbitStart, null)]);

        $engine->zombie(1);

        $this->assertSame([5], $game->hand[1]);
        $this->assertSame([], $game->used);
        $this->assertSame([], $engine->questionsFor(1));
    }

    public function testSnapshotOfAPendingOrderRestoresTheSameQuestions(): void
    {
        $game = $this->budgetGame(3);
        $engine = $this->engine($game);
        $engine->openWindow('B0', [$this->budgetStart()]);
        $snapshot = json_decode((string) json_encode($engine->snapshot()), true);

        $other = $this->engine($game);
        $this->assertNull($other->snapshot());
        $other->restore($snapshot);

        $this->assertSame('B0', $other->windowId());
        $this->assertEquals(
            array_map(static fn($q): array => $q->toArray(), $engine->questionsFor(1)),
            array_map(static fn($q): array => $q->toArray(), $other->questionsFor(1)),
        );
    }

    // ---- 通信確立のステージ（バリア） ----

    public function testStagedLinksAreHandedOverAtTheBarrierAndClearedAfterwards(): void
    {
        $game = (new FakeGame())->player(1);
        $game->satellite(1, SatelliteShape::Hammer, true, 'linker');
        $catalog = new ArrayCatalog([
            TestSpecs::spec('linker', Hook::Turned, PartId::EstablishLink),
            TestSpecs::spec('after_link', Hook::Linked, PartId::DrawCards),
        ]);
        $game->satellite(1, SatelliteShape::Retro, true, 'after_link');
        $game->deck = [3];
        $parts = (new PartRegistry())
            ->register(PartId::EstablishLink, new ScriptedResultPart(
                static fn(): Outcome => new Outcome([], [], [new StagedLinkRequest(1, 1, 42, 'S:1')]),
            ))
            ->register(PartId::DrawCards, new ScriptedResultPart(static function () use ($game): Outcome {
                $game->draw(1, 1);

                return Outcome::none();
            }));
        $engine = $this->engine($game, $parts, $catalog);

        $report = $engine->openWindow('TE', [new GameEvent(Hook::Turned, 1, 1)]);

        // コミットは状態 32 が全員分を一斉に行う。エンジンは積まれた分を渡すだけ
        $this->assertFalse($report->canClose());
        $this->assertCount(1, $report->stagedLinks);
        $this->assertSame(42, $report->stagedLinks[0]->cell);
        $this->assertSame([], $game->hand[1]);

        // コミットで起きた LINKED をプールに積んでからバリアをもう一度通す（世代 1）
        $engine->collect(new GameEvent(Hook::Linked, 1, 1, cell: 42, generation: 1));
        $second = $engine->barrier();

        $this->assertSame([3], $game->hand[1]);
        $this->assertSame([], $second->stagedLinks);
        $this->assertTrue($second->canClose());
    }
}
