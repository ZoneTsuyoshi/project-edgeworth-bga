<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Action;

use Bga\Games\ProjectEdgeworth\Data\CardLocation;
use Bga\Games\ProjectEdgeworth\Flow\Action\DeployTarget;
use Bga\Games\ProjectEdgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\ProjectEdgeworth\Flow\Action\RuleViolation;
use Bga\Games\ProjectEdgeworth\Flow\Action\StepMode;
use Bga\Games\ProjectEdgeworth\Board\AreaType;

final class DeployPhaseTest extends ActionTestCase
{
    private const MAIN_STATE = 61;
    private const BONUS_STATE = 62;

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

    public function testBonusParticipantsAreTheHoldersAndOffersDeployOrDraw(): void
    {
        $deploy = $this->phases->deploy();
        $this->assertSame([101, 103], $deploy->participants(StepMode::Bonus));
        $this->assertTrue($deploy->canAct(102, StepMode::Main));

        $runner = $this->phases->runner();
        $runner->enter($deploy, self::BONUS_STATE, StepMode::Bonus);
        $deckBefore = $this->phases->services()->cards->countIn(CardLocation::Deck);
        $handBefore = count($this->handOf(103));
        $runner->confirm($deploy, self::BONUS_STATE, 101, $this->deployChoice(4, $this->sat(101, 0)->id), StepMode::Bonus);
        $runner->confirm($deploy, self::BONUS_STATE, 103, ['op' => 'draw'], StepMode::Bonus);
        $result = $runner->resolve($deploy, self::BONUS_STATE, StepMode::Bonus);

        $this->assertSame($deckBefore - 1, $this->phases->services()->cards->countIn(CardLocation::Deck));
        $this->assertCount($handBefore + 1, $this->handOf(103));
        $this->assertCount(1, $result->outcomeOf(103)?->details['cards']);
        $this->assertSame('deploy', $result->outcomeOf(101)?->op);
    }

    public function testDrawIsNotAvailableAsMainAndDefaultIsPass(): void
    {
        $deploy = $this->phases->deploy();
        $this->assertSame(['op' => 'pass'], $deploy->defaultChoice(101, StepMode::Bonus));
        $this->expectException(RuleViolation::class);
        $deploy->validate(101, StepMode::Main, ['op' => 'draw']);
    }

    public function testSetupRoundHasEveryoneInTheBonusStepWithNoActiveSurcharge(): void
    {
        $this->globals->setRoundNo(0);
        $this->globals->setBonusHolders([]);
        $deploy = $this->phases->deploy();
        $this->assertSame([101, 102, 103], $deploy->participants(StepMode::Bonus));
        $this->assertSame(0, $deploy->cost(101, 1, $this->sat(101, 0))->activeSurcharge);
    }
}
