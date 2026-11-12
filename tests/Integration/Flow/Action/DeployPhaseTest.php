<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Action;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Data\DeployStage;
use Bga\Games\projectedgeworth\Flow\Action\ActionServices;
use Bga\Games\projectedgeworth\Flow\Action\DeployTarget;
use Bga\Games\projectedgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Board\AreaType;

final class DeployPhaseTest extends ActionTestCase
{
    private const MAIN_STATE = 61;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startGame(3);
        // 手札: P001（コスト 5）、P004（3）、P010（4）
        $this->setHand(101, [1, 4, 10]);
        $this->setMaya(101, 20);
        $this->globals->setBonusHolders([2 => [101, 103]]);
    }

    /** @return array<string, mixed> */
    private function deployChoice(int $card, int $satId, array $sell = []): array
    {
        return ['op' => 'deploy', 'card' => $card, 'sat' => $satId, 'sell' => $sell];
    }

    public function testCostIsCardCostForInactiveSatelliteAndPlusFiveForActive(): void
    {
        $inactive = $this->sat(101, 0);
        $active = $this->sat(101, 1);
        $this->activate($active->id, $this->cellOf(AreaType::SEA));
        $deploy = $this->phases->deploy();

        $this->assertSame(5, $deploy->cost(101, 1, $inactive)->total());
        $this->assertSame(10, $deploy->cost(101, 1, $this->sat(101, 1))->total());
        $targets = $deploy->targets(101, 1);
        $this->assertCount(5, $targets);
        $this->assertSame(5, $targets[0]->cost->total());
        $this->assertSame(10, $targets[1]->cost->total());
        $this->assertSame(5, $targets[1]->cost->activeSurcharge);
    }

    public function testReductionsAreSubtractedAndFloorIsAppliedOnceAtTheEnd(): void
    {
        $this->activate($this->sat(101, 1)->id, $this->cellOf(AreaType::SEA));
        $this->hooks->deployReduction = 6;
        $deploy = $this->phases->deploy();
        // 3(P004) + 5(稼働) − 6 = 2。稼働コストを足す前に 0 へ切り上げない
        $this->assertSame(2, $deploy->cost(101, 4, $this->sat(101, 1))->total());
        // 3 − 6 → 0（下限）
        $this->assertSame(0, $deploy->cost(101, 4, $this->sat(101, 0))->total());
    }

    public function testFourCardsPerSatelliteMaximum(): void
    {
        $sat = $this->sat(101, 0);
        $this->setHand(101, [1, 2, 3, 4, 5, 6]);
        foreach ([1, 2, 3, 4] as $card) {
            $this->deployDirect(101, $card, $sat->id);
        }
        $target = $this->phases->deploy()->targets(101, 5)[0];
        $this->assertSame(DeployTarget::BLOCKED_FULL, $target->blocked);
        $this->assertFalse($target->isSelectable());

        $this->expectException(RuleViolation::class);
        $this->phases->deploy()->validate(101, StepMode::Main, $this->deployChoice(5, $sat->id));
    }

    public function testForbiddenSatelliteCannotReceiveCards(): void
    {
        $sat = $this->sat(101, 0);
        $this->hooks->forbidden[ForbiddenAction::DeployTo->value] = [$sat->id];
        $this->assertSame(DeployTarget::BLOCKED_FORBIDDEN, $this->phases->deploy()->targets(101, 1)[0]->blocked);
        $this->expectException(RuleViolation::class);
        $this->phases->deploy()->validate(101, StepMode::Main, $this->deployChoice(1, $sat->id));
    }

    public function testCardMustBeInHandAndSatelliteMustBeOwn(): void
    {
        $this->expectException(RuleViolation::class);
        $this->phases->deploy()->validate(101, StepMode::Main, $this->deployChoice(2, $this->sat(101, 0)->id));
    }

    public function testForeignSatelliteIsRejected(): void
    {
        $this->expectException(RuleViolation::class);
        $this->phases->deploy()->validate(101, StepMode::Main, $this->deployChoice(1, $this->sat(102, 0)->id));
    }

    public function testShortOfMayaNeedsSalesAndOverSellingIsAllowed(): void
    {
        $this->setMaya(101, 1);
        $sat = $this->sat(101, 0);
        $deploy = $this->phases->deploy();
        // P001 のコスト 5、所持 1。不足 4 → 売却 2 枚（P004・P010）で届く。配備するカード自身は売れない
        $deploy->validate(101, StepMode::Main, $this->deployChoice(1, $sat->id, [4, 10]));
        try {
            $deploy->validate(101, StepMode::Main, $this->deployChoice(1, $sat->id, [4]));
            $this->fail('expected RuleViolation');
        } catch (RuleViolation) {
            $this->addToAssertionCount(1);
        }
        try {
            $deploy->validate(101, StepMode::Main, $this->deployChoice(1, $sat->id, [1, 4]));
            $this->fail('expected RuleViolation');
        } catch (RuleViolation) {
            $this->addToAssertionCount(1);
        }
    }

    public function testMainStepDeploysSellsAndPaysAtOnce(): void
    {
        $this->setMaya(101, 1);
        $this->setMaya(102, 30);
        $this->setHand(102, [34]);
        $deploy = $this->phases->deploy();
        $runner = $this->phases->runner();
        $entry = $runner->enter($deploy, self::MAIN_STATE);
        $this->assertSame([101, 102, 103], $entry->participants);

        $sat101 = $this->sat(101, 2)->id;
        $sat102 = $this->sat(102, 0)->id;
        $runner->confirm($deploy, self::MAIN_STATE, 101, $this->deployChoice(1, $sat101, [4, 10]));
        $runner->confirm($deploy, self::MAIN_STATE, 102, $this->deployChoice(34, $sat102));
        $runner->confirm($deploy, self::MAIN_STATE, 103, ['op' => 'pass']);
        $result = $runner->resolve($deploy, self::MAIN_STATE);

        // 101: 1 + 売却 6 − コスト 5 = 2。手札は 0 枚、P001 は第 1 段
        $this->assertSame(2, $this->maya(101));
        $this->assertSame([], $this->handOf(101));
        $cards = $this->phases->services()->cards;
        $this->assertSame([1], $cards->deployedIds($sat101));
        $this->assertSame(1, $result->outcomeOf(101)?->details['tier']);
        $this->assertSame([4, 10], $result->outcomeOf(101)?->details['sold']);
        // 102: P034（コスト 5）
        $this->assertSame(25, $this->maya(102));
        $this->assertSame([34], $cards->deployedIds($sat102));
        $this->assertTrue($result->outcomeOf(103)?->isPass());
    }

    public function testCardsStackOnTopInDeploymentOrder(): void
    {
        $sat = $this->sat(101, 0)->id;
        $deploy = $this->phases->deploy();
        foreach ([4, 1] as $card) {
            $deploy->resolve(StepMode::Main, [101 => $this->deployChoice($card, $sat)]);
        }
        $this->assertSame([4, 1], $this->phases->services()->cards->deployedIds($sat));
    }

    public function testUnaffordableAtResolutionBecomesPassWithoutSelling(): void
    {
        $sat = $this->sat(101, 0)->id;
        $this->setMaya(101, 0);
        $result = $this->phases->deploy()->resolve(StepMode::Main, [101 => $this->deployChoice(1, $sat, [4])]);
        $outcome = $result->outcomeOf(101);
        $this->assertTrue($outcome?->isPass());
        $this->assertTrue($outcome?->paymentFailed);
        $this->assertSame([1, 4, 10], $this->handOf(101));
        $this->assertSame(0, $this->maya(101));
    }

    public function testPlayerWithNothingToDoIsConfirmedAutomatically(): void
    {
        $this->setHand(102, []);
        $deploy = $this->phases->deploy();
        $entry = $this->phases->runner()->enter($deploy, self::MAIN_STATE);
        $this->assertSame([102], $entry->autoConfirmed);
        $this->assertSame([101, 103], $entry->active);
        $this->assertTrue($this->phases->services()->choices->isAuto(102, self::MAIN_STATE));
    }

    /** @return array<string, mixed> */
    private function withExtra(array $choice, array $extra): array
    {
        return $choice + ['extra' => $extra];
    }

    private function cost(int $card): int
    {
        return ActionServices::project($card)->cost;
    }

    /** @return array<string, mixed> ステージ済み（S1）の確定内容: 2 枚目を配備する */
    private function second(int $card, int $satId, array $sell = []): array
    {
        return ['op' => 'staged', 'extra' => ['op' => 'deploy', 'card' => $card, 'sat' => $satId, 'sell' => $sell]];
    }

    /** 1 枚目をステージする（支払いと配備。通知・効果・スナップショットは状態機械側の役目なので、記録だけ最小限に作る） */
    private function stage(int $playerId, array $choice): void
    {
        $outcome = $this->phases->deploy()->stageFirst($playerId, $choice);
        $this->phases->services()->deployStages->save(new DeployStage(
            $playerId,
            (int) $outcome->details['card'],
            (int) $outcome->details['sat'],
            (int) $outcome->details['tier'],
            (int) $outcome->details['cost'],
            array_values($outcome->details['sold']),
            false,
            [],
            [],
            [],
        ));
    }

    public function testEveryoneParticipatesAndOnlyHoldersMayAddAnExtraAction(): void
    {
        $deploy = $this->phases->deploy();
        $this->assertSame([101, 102, 103], $deploy->participants(StepMode::Main));
        $this->assertTrue($deploy->isBonusHolder(101));
        $this->assertFalse($deploy->isBonusHolder(102));
        $this->setHand(102, [34, 35]);
        $sat = $this->sat(102, 0)->id;

        $this->expectException(RuleViolation::class);
        $deploy->validate(102, StepMode::Main, $this->withExtra($this->deployChoice(34, $sat), ['op' => 'draw']));
    }

    public function testNonHolderCannotStageASecondCard(): void
    {
        $this->setHand(102, [34, 35]);
        $this->expectException(RuleViolation::class);
        $this->phases->deploy()->stageFirst(102, $this->deployChoice(34, $this->sat(102, 0)->id));
    }

    public function testExtraDeployIsNotAcceptedBeforeTheFirstCardIsStaged(): void
    {
        // 追加の配備は [Deploy another card] で 1 枚目を先に確定してから選ぶ（S0 の確定には含められない）
        $this->expectException(RuleViolation::class);
        $this->phases->deploy()->validate(101, StepMode::Main, $this->withExtra(
            $this->deployChoice(1, $this->sat(101, 0)->id),
            ['op' => 'deploy', 'card' => 4, 'sat' => $this->sat(101, 1)->id],
        ));
    }

    public function testStagePaysAndDeploysTheFirstCardAtOnceAndOnlyOnce(): void
    {
        $a = $this->sat(101, 0)->id;
        $this->stage(101, $this->deployChoice(1, $a));
        $cards = $this->phases->services()->cards;
        $this->assertSame([1], $cards->deployedIds($a));
        $this->assertSame(20 - $this->cost(1), $this->maya(101));
        $this->assertSame([4, 10], $this->handOf(101));
        $this->assertTrue($this->phases->deploy()->isStaged(101));

        try {
            $this->phases->deploy()->stageFirst(101, $this->deployChoice(4, $a));
            $this->fail('staging twice is rejected');
        } catch (RuleViolation) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(20 - $this->cost(1), $this->maya(101), '拒否は状態を変えない');
    }

    public function testStageFailureLeavesTheStateUntouched(): void
    {
        $this->setMaya(101, 1);
        try {
            $this->phases->deploy()->stageFirst(101, $this->deployChoice(1, $this->sat(101, 0)->id));
            $this->fail('expected RuleViolation');
        } catch (RuleViolation) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(1, $this->maya(101));
        $this->assertSame([1, 4, 10], $this->handOf(101));
        $this->assertFalse($this->phases->deploy()->isStaged(101));
    }

    public function testHolderDeploysASecondCardAfterTheFirstAndPaysEachCardSeparately(): void
    {
        $deploy = $this->phases->deploy();
        $a = $this->sat(101, 0)->id;
        $b = $this->sat(101, 1)->id;
        $this->stage(101, $this->deployChoice(1, $a));
        $choice = $this->second(4, $b);
        $deploy->validate(101, StepMode::Main, $choice);

        $result = $deploy->resolve(StepMode::Main, [101 => $choice]);
        $outcomes = $result->outcomesOf(101);
        $this->assertCount(1, $outcomes, '1 枚目はステージ済みなので、コミットは 2 枚目だけ');
        $this->assertSame(4, $outcomes[0]->details['card']);
        $this->assertSame($this->cost(4), $outcomes[0]->details['cost']);
        $this->assertSame(20 - $this->cost(1) - $this->cost(4), $this->maya(101));
        $this->assertSame([10], $this->handOf(101));
        $cards = $this->phases->services()->cards;
        $this->assertSame([1], $cards->deployedIds($a));
        $this->assertSame([4], $cards->deployedIds($b));
    }

    public function testStagedPlayerWithoutASecondCardCommitsNothingMore(): void
    {
        $deploy = $this->phases->deploy();
        $a = $this->sat(101, 0)->id;
        $this->stage(101, $this->deployChoice(1, $a));
        $mayaAfterStage = $this->maya(101);
        $this->assertSame(['op' => 'staged'], $deploy->defaultChoice(101, StepMode::Main), 'zombie はステージを残して 2 枚目なしで確定');
        foreach ([['op' => 'staged'], ['op' => 'staged', 'extra' => ['op' => 'pass']]] as $choice) {
            $deploy->validate(101, StepMode::Main, $choice);
            $this->assertSame([], $deploy->resolve(StepMode::Main, [101 => $choice])->outcomesOf(101));
        }
        $this->assertSame($mayaAfterStage, $this->maya(101), '再支払いしない');
        $this->assertSame([1], $this->phases->services()->cards->deployedIds($a));
    }

    public function testNormalizeDropsTheOuterOpOfAStagedPlayer(): void
    {
        $deploy = $this->phases->deploy();
        $a = $this->sat(101, 0)->id;
        $choice = $this->withExtra($this->deployChoice(4, $a), ['op' => 'deploy', 'card' => 10, 'sat' => $a]);
        $this->assertSame($choice, $deploy->normalize(101, $choice), '未ステージはそのまま');
        $this->stage(101, $this->deployChoice(1, $a));
        $this->assertSame(['op' => 'staged', 'extra' => $choice['extra']], $deploy->normalize(101, $choice));
        $this->assertSame(['op' => 'staged'], $deploy->normalize(101, $this->deployChoice(4, $a)));
    }

    public function testSecondCardOnTheSameSatelliteStacksAboveTheFirst(): void
    {
        $sat = $this->sat(101, 0)->id;
        $this->stage(101, $this->deployChoice(1, $sat));
        $choice = $this->second(4, $sat);
        $this->phases->deploy()->validate(101, StepMode::Main, $choice);
        $result = $this->phases->deploy()->resolve(StepMode::Main, [101 => $choice]);

        $this->assertSame([2], array_map(static fn($o): int => $o->details['tier'], $result->outcomesOf(101)));
        $this->assertSame([1, 4], $this->phases->services()->cards->deployedIds($sat));
    }

    public function testStagedFirstCardCountsTowardTheFourCardLimitOfTheSecond(): void
    {
        $sat = $this->sat(101, 0)->id;
        $this->setHand(101, [1, 2, 3, 4, 5, 6]);
        $this->deployDirect(101, 5, $sat);
        $this->deployDirect(101, 6, $sat);
        // 2 枚付いている衛星へ 2 枚: ちょうど 4 枚
        $this->stage(101, $this->deployChoice(1, $sat));
        $this->phases->deploy()->validate(101, StepMode::Main, $this->second(2, $sat));
        $this->assertSame(3, $this->phases->deploy()->targets(101, 2)[0]->deployedCount, '候補は実際の盤面（1 枚目を含む 3 枚）');

        // 3 枚付いている衛星なら、1 枚目のステージで 4 枚になり、2 枚目は同じ衛星に配備できない
        $sat2 = $this->sat(101, 1)->id;
        $this->deployDirect(101, 7, $sat2);
        $this->deployDirect(101, 8, $sat2);
        $this->deployDirect(101, 9, $sat2);
        $this->setHand(101, [2, 3, 4, 10]);
        $this->phases->services()->deployStages->remove(101);
        $this->stage(101, $this->deployChoice(2, $sat2));
        $this->assertSame(DeployTarget::BLOCKED_FULL, $this->phases->deploy()->targets(101, 3)[1]->blocked);
        $this->expectException(RuleViolation::class);
        $this->phases->deploy()->validate(101, StepMode::Main, $this->second(3, $sat2));
    }

    public function testStageIsRejectedWhenTheSatelliteIsAlreadyFull(): void
    {
        $sat = $this->sat(101, 0);
        $this->setHand(101, [1, 2, 3, 4, 5, 6]);
        foreach ([1, 2, 3, 4] as $card) {
            $this->deployDirect(101, $card, $sat->id);
        }
        $this->expectException(RuleViolation::class);
        $this->phases->deploy()->stageFirst(101, $this->deployChoice(5, $sat->id));
    }

    public function testTargetsAfterStageCountTheFirstCardOnTheRealBoard(): void
    {
        $sat = $this->sat(101, 0)->id;
        $this->deployDirect(101, 5, $sat);
        $this->deployDirect(101, 6, $sat);
        $this->deployDirect(101, 7, $sat);
        $this->setHand(101, [1, 4]);
        $deploy = $this->phases->deploy();
        $this->assertNull($deploy->targets(101, 1)[0]->blocked);
        $this->assertSame(3, $deploy->targets(101, 1)[0]->deployedCount);
        $this->stage(101, $this->deployChoice(1, $sat));
        $this->assertSame(DeployTarget::BLOCKED_FULL, $deploy->targets(101, 4)[0]->blocked);
        $this->assertSame(4, $deploy->targets(101, 4)[0]->deployedCount);
    }

    public function testActiveSurchargeIsPaidPerCard(): void
    {
        $sat = $this->sat(101, 0);
        $this->activate($sat->id, $this->cellOf(AreaType::SEA));
        $deploy = $this->phases->deploy();
        $this->assertSame($this->cost(1) + 5, $deploy->plan(101, $this->deployChoice(1, $sat->id))->totalCost());
        $this->stage(101, $this->deployChoice(1, $sat->id));
        $this->assertSame(20 - $this->cost(1) - 5, $this->maya(101));

        $choice = $this->second(4, $sat->id);
        $this->assertSame($this->cost(4) + 5, $deploy->planSecond(101, $choice)->totalCost());
        $deploy->resolve(StepMode::Main, [101 => $choice]);
        $this->assertSame(20 - $this->cost(1) - 5 - $this->cost(4) - 5, $this->maya(101));
    }

    public function testPayabilityAndSalesAreJudgedPerCard(): void
    {
        $this->setHand(101, [1, 4, 10, 2, 3, 5, 6]);
        $this->setMaya(101, $this->cost(1));
        $a = $this->sat(101, 0)->id;
        $b = $this->sat(101, 1)->id;
        $deploy = $this->phases->deploy();
        $this->stage(101, $this->deployChoice(1, $a));
        $this->assertSame(0, $this->maya(101));

        // 2 枚目のコストは 2 枚目の分だけ。売却も 2 枚目の分で判定する
        $need = (int) ceil($this->cost(4) / 3);
        $others = [10, 2, 3, 5, 6];
        $deploy->validate(101, StepMode::Main, $this->second(4, $b, array_slice($others, 0, $need)));
        try {
            $deploy->validate(101, StepMode::Main, $this->second(4, $b, array_slice($others, 0, $need - 1)));
            $this->fail('expected RuleViolation');
        } catch (RuleViolation) {
            $this->addToAssertionCount(1);
        }
        // 配備する 2 枚目自身は売れない
        try {
            $deploy->validate(101, StepMode::Main, $this->second(4, $b, [4, 10, 2, 3, 5, 6]));
            $this->fail('expected RuleViolation');
        } catch (RuleViolation) {
            $this->addToAssertionCount(1);
        }

        $sold = array_slice($others, 0, $need);
        $result = $deploy->resolve(StepMode::Main, [101 => $this->second(4, $b, $sold)]);
        $this->assertSame($sold, $result->outcomesOf(101)[0]->details['sold']);
        $this->assertSame(3 * $need - $this->cost(4), $this->maya(101));
    }

    public function testStagePaymentCanIncludeSales(): void
    {
        $this->setMaya(101, 1);
        $a = $this->sat(101, 0)->id;
        $this->stage(101, $this->deployChoice(1, $a, [4, 10]));
        $this->assertSame(1 + 6 - $this->cost(1), $this->maya(101));
        $this->assertSame([], $this->handOf(101));
        $this->assertSame([4, 10], $this->phases->services()->deployStages->find(101)?->sold);
    }

    public function testUnaffordableSecondCardIsDroppedAndTheStagedFirstCardRemains(): void
    {
        $a = $this->sat(101, 0)->id;
        $this->stage(101, $this->deployChoice(1, $a));
        $this->setMaya(101, 0);
        $outcomes = $this->phases->deploy()->resolve(StepMode::Main, [101 => $this->second(4, $a)])->outcomesOf(101);
        $this->assertSame([], $outcomes);
        $this->assertSame([4, 10], $this->handOf(101));
        $this->assertSame([1], $this->phases->services()->cards->deployedIds($a));
    }

    public function testExtraDrawGivesACardThatCannotBeUsedInTheSameChoice(): void
    {
        $deploy = $this->phases->deploy();
        $cards = $this->phases->services()->cards;
        $top = $cards->idsIn(CardLocation::Deck)[0];
        $deckBefore = $cards->countIn(CardLocation::Deck);
        $sat = $this->sat(101, 0)->id;
        $choice = $this->withExtra($this->deployChoice(4, $sat), ['op' => 'draw']);
        $deploy->validate(101, StepMode::Main, $choice);

        // 選ぶ時点では手札にないので、引くカードを配備には使えない
        try {
            $deploy->validate(101, StepMode::Main, $this->withExtra($this->deployChoice($top, $sat), ['op' => 'pass']));
            $this->fail('expected RuleViolation');
        } catch (RuleViolation) {
            $this->addToAssertionCount(1);
        }

        $result = $deploy->resolve(StepMode::Main, [101 => $choice]);
        $outcomes = $result->outcomesOf(101);
        $this->assertSame(['deploy', 'draw'], array_map(static fn($o): string => $o->op, $outcomes));
        $this->assertCount(1, $outcomes[1]->details['cards']);
        $this->assertSame($deckBefore - 1, $cards->countIn(CardLocation::Deck));
        $this->assertSame([4], $cards->deployedIds($sat));
        $this->assertCount(3, $this->handOf(101), '3 - 1 + 1');
    }

    public function testHolderMayPassTheMainDeploymentAndOnlyDraw(): void
    {
        $deploy = $this->phases->deploy();
        $cards = $this->phases->services()->cards;
        $deploy->validate(101, StepMode::Main, ['op' => 'pass', 'extra' => ['op' => 'draw']]);
        $deploy->validate(101, StepMode::Main, ['op' => 'pass', 'extra' => ['op' => 'pass']]);
        $result = $deploy->resolve(StepMode::Main, [101 => ['op' => 'pass', 'extra' => ['op' => 'draw']]]);
        $this->assertSame(['draw'], array_map(static fn($o): string => $o->op, $result->outcomesOf(101)));
        $this->assertCount(4, $this->handOf(101));
        $this->assertSame([], $cards->deployedIds($this->sat(101, 0)->id));
    }

    public function testDrawIsNotAllowedAfterStagingAndDrawIsNotAMainOperation(): void
    {
        $deploy = $this->phases->deploy();
        $sat = $this->sat(101, 0)->id;
        $this->stage(101, $this->deployChoice(1, $sat));
        try {
            $deploy->validate(101, StepMode::Main, ['op' => 'staged', 'extra' => ['op' => 'draw']]);
            $this->fail('expected RuleViolation');
        } catch (RuleViolation) {
            $this->addToAssertionCount(1);
        }
        $this->phases->services()->deployStages->remove(101);
        $this->assertSame(['op' => 'pass'], $deploy->defaultChoice(101, StepMode::Main));
        $this->expectException(RuleViolation::class);
        $deploy->validate(101, StepMode::Main, ['op' => 'draw']);
    }

    public function testHolderWhoCanOnlyDrawStillActs(): void
    {
        $this->setHand(101, []);
        $deploy = $this->phases->deploy();
        $this->assertTrue($deploy->canAct(101, StepMode::Main), 'ボーナス保持者は 1 枚引ける');
        $this->setHand(102, []);
        $this->assertFalse($deploy->canAct(102, StepMode::Main));
    }

    public function testSetupRoundHasEveryoneAsBonusHolderWithNoActiveSurcharge(): void
    {
        $this->globals->setRoundNo(0);
        $this->globals->setBonusHolders([2 => [101, 102, 103]]);
        $deploy = $this->phases->deploy();
        $this->assertSame([101, 102, 103], $deploy->participants(StepMode::Main));
        $this->assertTrue($deploy->isBonusHolder(102));
        $this->assertSame(0, $deploy->cost(101, 1, $this->sat(101, 0))->activeSurcharge);

        $this->setHand(102, [34, 35]);
        $this->setMaya(102, 20);
        $this->stage(102, $this->deployChoice(34, $this->sat(102, 0)->id));
        $deploy->validate(102, StepMode::Main, $this->second(35, $this->sat(102, 0)->id));
    }

    public function testMainStepConfirmsAllAtOnceThroughTheRunner(): void
    {
        $deploy = $this->phases->deploy();
        $runner = $this->phases->runner();
        $entry = $runner->enter($deploy, self::MAIN_STATE);
        $this->assertSame([101, 102, 103], $entry->participants);
        $this->stage(101, $this->deployChoice(1, $this->sat(101, 0)->id));
        $runner->confirm($deploy, self::MAIN_STATE, 101, $this->second(4, $this->sat(101, 1)->id));
        $runner->confirm($deploy, self::MAIN_STATE, 102, ['op' => 'pass']);
        $runner->confirm($deploy, self::MAIN_STATE, 103, ['op' => 'pass', 'extra' => ['op' => 'draw']]);
        $result = $runner->resolve($deploy, self::MAIN_STATE);

        $this->assertSame(['deploy'], array_map(static fn($o): string => $o->op, $result->outcomesOf(101)));
        $this->assertSame(4, $result->outcomesOf(101)[0]->details['card']);
        $this->assertTrue($result->outcomeOf(102)?->isPass());
        $this->assertSame(['draw'], array_map(static fn($o): string => $o->op, $result->outcomesOf(103)));
    }

    public function testStagedPlayerWhoNeverConfirmedGetsTheDefaultAtResolution(): void
    {
        $deploy = $this->phases->deploy();
        $runner = $this->phases->runner();
        $runner->enter($deploy, self::MAIN_STATE);
        $this->stage(101, $this->deployChoice(1, $this->sat(101, 0)->id));
        $runner->zombie($deploy, self::MAIN_STATE, 101);
        $runner->confirm($deploy, self::MAIN_STATE, 102, ['op' => 'pass']);
        $runner->confirm($deploy, self::MAIN_STATE, 103, ['op' => 'pass']);
        $result = $runner->resolve($deploy, self::MAIN_STATE);

        $this->assertSame([], $result->outcomesOf(101), 'zombie の S1: ステージ済みの 1 枚目を残して 2 枚目なし');
        $this->assertSame(20 - $this->cost(1), $this->maya(101));
        $this->assertSame([1], $this->phases->services()->cards->deployedIds($this->sat(101, 0)->id));
    }
}
