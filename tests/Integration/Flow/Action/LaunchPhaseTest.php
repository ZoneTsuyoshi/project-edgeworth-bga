<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Action;

use Bga\Games\projectedgeworth\Board\AreaType;
use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\projectedgeworth\Flow\Action\LaunchOption;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Flow\Action\TurnOption;

final class LaunchPhaseTest extends ActionTestCase
{
    private const MAIN_STATE = 71;
    private const BONUS_STATE = 72;

    private int $pad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startGame(3);
        $this->pad = $this->cellOf(AreaType::LAND);
        $this->pad(101, $this->pad);
        $this->setMaya(101, 30);
        $this->globals->setBonusHolders([3 => [101]]);
    }

    /** @return array<string, mixed> */
    private function launch(int $satId, string $dir = 'NE', array $sell = []): array
    {
        return ['op' => 'launch', 'sat' => $satId, 'dir' => $dir, 'sell' => $sell];
    }

    public function testCannotLaunchWithoutDeployedCards(): void
    {
        $sat = $this->sat(101, 0);
        $option = $this->phases->launch()->launchOptions(101)[0];
        $this->assertSame(LaunchOption::BLOCKED_NO_PROJECT, $option->blocked);
        $this->assertSame(0, $option->deployedCount);
        $this->expectException(RuleViolation::class);
        $this->phases->launch()->validate(101, StepMode::Main, $this->launch($sat->id));
    }

    public function testLaunchPlacesSatelliteOnTheLaunchBaseAndPaysTheCardCost(): void
    {
        $sat = $this->sat(101, 0);
        $this->deployDirect(101, 1, $sat->id);
        $result = $this->phases->launch()->resolve(StepMode::Main, [101 => $this->launch($sat->id, 'SW')]);

        $after = $this->phases->services()->satellites->get($sat->id);
        $this->assertTrue($after->isActive());
        $this->assertSame($this->pad, $after->cell);
        $this->assertSame(Direction::SW, $after->direction);
        $this->assertSame(0, $after->fuelUsed);
        // HAMMER の打ち上げコストは 10
        $this->assertSame(20, $this->maya(101));
        $this->assertSame('launch', $result->outcomeOf(101)?->op);
    }

    public function testLaunchCostFloorsAtZeroAfterReduction(): void
    {
        $sat = $this->sat(101, 0);
        $this->deployDirect(101, 1, $sat->id);
        $this->hooks->launchReduction = 3;
        $this->assertSame(7, $this->phases->launch()->launchOptions(101)[0]->cost);
        $this->hooks->launchReduction = 99;
        $this->assertSame(0, $this->phases->launch()->launchOptions(101)[0]->cost);
    }

    public function testAllSixDirectionsAreAcceptedAndOthersRefused(): void
    {
        $sat = $this->sat(101, 0);
        $this->deployDirect(101, 1, $sat->id);
        foreach (['N', 'S', '', 'ne'] as $dir) {
            try {
                $this->phases->launch()->validate(101, StepMode::Main, $this->launch($sat->id, $dir));
                $this->fail("expected RuleViolation for '$dir'");
            } catch (RuleViolation) {
                $this->addToAssertionCount(1);
            }
        }
        foreach (['NE', 'E', 'SE', 'SW', 'W', 'NW'] as $dir) {
            $this->phases->launch()->validate(101, StepMode::Main, $this->launch($sat->id, $dir));
        }
    }

    /** 作者回答 2026-10-20: 打ち上げも転回も E / W を選べ、そのまま保存される */
    public function testLaunchAndTurnWithEastAndWest(): void
    {
        $sat = $this->sat(101, 0);
        $this->deployDirect(101, 1, $sat->id);
        $this->phases->launch()->resolve(StepMode::Main, [101 => $this->launch($sat->id, 'E')]);
        $this->assertSame(Direction::E, $this->phases->services()->satellites->get($sat->id)->direction);

        $this->phases->launch()->resolve(StepMode::Main, [101 => ['op' => 'turn', 'sat' => $sat->id, 'dir' => 'W']]);
        $this->assertSame(Direction::W, $this->phases->services()->satellites->get($sat->id)->direction);
    }

    public function testActiveSatelliteCannotBeLaunchedAgainButLandedOneCan(): void
    {
        $sat = $this->sat(101, 0);
        $this->deployDirect(101, 1, $sat->id);
        $this->activate($sat->id, $this->pad);
        $this->assertSame(LaunchOption::BLOCKED_ACTIVE, $this->phases->launch()->launchOptions(101)[0]->blocked);

        // 着陸（非稼働に戻る）後は、配備されたカードが残ったまま再び打ち上げられる
        $this->phases->services()->satellites->deactivate($sat->id);
        $this->assertNull($this->phases->launch()->launchOptions(101)[0]->blocked);
    }

    public function testPlayerWithoutLaunchBaseCannotLaunch(): void
    {
        $sat = $this->sat(102, 0);
        $this->deployDirect(102, 2, $sat->id);
        $this->assertSame(LaunchOption::BLOCKED_NO_PAD, $this->phases->launch()->launchOptions(102)[0]->blocked);
    }

    public function testLaunchCanBePaidBySellingWhenTheHandIsEnough(): void
    {
        $sat = $this->sat(101, 0);
        $this->deployDirect(101, 1, $sat->id);
        $this->setMaya(101, 5);
        $launch = $this->phases->launch();

        // コスト 10、所持 5。手札 1 枚（3）では届かず、2 枚（6）なら届く
        $this->setHand(101, [2]);
        $this->assertFalse($launch->launchOptions(101)[0]->isSelectable());
        $this->setHand(101, [2, 3]);
        $this->assertTrue($launch->launchOptions(101)[0]->isSelectable());
        $launch->validate(101, StepMode::Main, $this->launch($sat->id, 'NE', [2, 3]));
        try {
            $launch->validate(101, StepMode::Main, $this->launch($sat->id, 'NE', [2]));
            $this->fail('expected RuleViolation');
        } catch (RuleViolation) {
            $this->addToAssertionCount(1);
        }
    }

    public function testTurnChangesDirectionOnlyAndSameDirectionIsAllowed(): void
    {
        $sat = $this->sat(101, 0);
        $this->activate($sat->id, $this->pad, Direction::NE);
        $launch = $this->phases->launch();
        $turn = ['op' => 'turn', 'sat' => $sat->id, 'dir' => 'SW'];

        $result = $launch->resolve(StepMode::Main, [101 => $turn]);
        $this->assertSame(Direction::SW, $this->phases->services()->satellites->get($sat->id)->direction);
        $this->assertFalse($result->outcomeOf(101)?->details['same']);
        $this->assertSame(30, $this->maya(101));

        $again = $launch->resolve(StepMode::Main, [101 => ['op' => 'turn', 'sat' => $sat->id, 'dir' => 'SW']]);
        $this->assertTrue($again->outcomeOf(101)?->details['same']);
    }

    public function testTurnNeedsAnInactiveSatelliteAndAnUnforbiddenTarget(): void
    {
        $launch = $this->phases->launch();
        foreach ($this->sats(101) as $sat) {
            $this->activate($sat->id, $this->pad);
        }
        $options = $launch->turnOptions(101);
        $this->assertCount(5, $options);
        $this->assertSame(TurnOption::BLOCKED_NO_INACTIVE, $options[0]->blocked);
        $this->assertFalse($launch->canRaiseBudget(101));
        $this->assertFalse($launch->canAct(101, StepMode::Main));
        try {
            $launch->validate(101, StepMode::Main, ['op' => 'turn', 'sat' => $options[0]->satellite->id, 'dir' => 'NE']);
            $this->fail('expected RuleViolation');
        } catch (RuleViolation) {
            $this->addToAssertionCount(1);
        }

        $this->phases->services()->satellites->deactivate($this->sat(101, 4)->id);
        $this->assertTrue($launch->turnOptions(101)[0]->isSelectable());
        $this->hooks->forbidden[ForbiddenAction::Turn->value] = [$this->sat(101, 0)->id];
        $this->assertSame(TurnOption::BLOCKED_FORBIDDEN, $launch->turnOptions(101)[0]->blocked);
        $this->assertTrue($launch->turnOptions(101)[1]->isSelectable());
    }

    public function testBonusOffersTurnOrBudgetButNotLaunch(): void
    {
        $sat = $this->sat(101, 0);
        $this->deployDirect(101, 1, $sat->id);
        $launch = $this->phases->launch();
        $this->assertSame([101], $launch->participants(StepMode::Bonus));

        try {
            $launch->validate(101, StepMode::Bonus, $this->launch($sat->id));
            $this->fail('expected RuleViolation');
        } catch (RuleViolation) {
            $this->addToAssertionCount(1);
        }
        try {
            $launch->validate(101, StepMode::Main, ['op' => 'budget']);
            $this->fail('expected RuleViolation');
        } catch (RuleViolation) {
            $this->addToAssertionCount(1);
        }

        $players = $this->phases->services()->players;
        $before = $players->budget(101);
        $result = $launch->resolve(StepMode::Bonus, [101 => ['op' => 'budget']]);
        $this->assertSame($before + 1, $players->budget(101));
        $this->assertSame('budget', $result->outcomeOf(101)?->op);
    }

    public function testBudgetBonusNeedsAnInactiveSatellite(): void
    {
        foreach ($this->sats(101) as $sat) {
            $this->activate($sat->id, $this->pad);
        }
        $this->expectException(RuleViolation::class);
        $this->phases->launch()->validate(101, StepMode::Bonus, ['op' => 'budget']);
    }

    public function testBudgetHasNoUpperLimit(): void
    {
        $players = $this->phases->services()->players;
        $players->setBudget(101, 50);
        $this->phases->launch()->resolve(StepMode::Bonus, [101 => ['op' => 'budget']]);
        $this->assertSame(51, $players->budget(101));
    }

    public function testUnaffordableLaunchBecomesPass(): void
    {
        $sat = $this->sat(101, 0);
        $this->deployDirect(101, 1, $sat->id);
        $this->setHand(101, []);
        $this->setMaya(101, 9);
        $result = $this->phases->launch()->resolve(StepMode::Main, [101 => $this->launch($sat->id)]);
        $this->assertTrue($result->outcomeOf(101)?->paymentFailed);
        $this->assertFalse($this->phases->services()->satellites->get($sat->id)->isActive());
        $this->assertSame(9, $this->maya(101));
    }

    public function testLaunchFromABaseAlreadyHoldingOwnSatelliteStacks(): void
    {
        $a = $this->sat(101, 0);
        $b = $this->sat(101, 1);
        $this->deployDirect(101, 1, $a->id);
        $this->deployDirect(101, 2, $b->id);
        // 発射拠点に既に自分の衛星がいても打ち上げられる（重なる）
        $this->phases->launch()->resolve(StepMode::Main, [101 => $this->launch($a->id)]);
        $this->phases->launch()->resolve(StepMode::Main, [101 => $this->launch($b->id)]);
        $this->assertCount(2, $this->phases->services()->satellites->activeAtCell($this->pad));
    }

    public function testStepFlowAutoConfirmsPlayersWhoCannotAct(): void
    {
        $launch = $this->phases->launch();
        $entry = $this->phases->runner()->enter($launch, self::MAIN_STATE);
        // 誰も配備していない = 打ち上げできず、稼働中の衛星もない
        $this->assertSame([101, 102, 103], $entry->autoConfirmed);
        $this->assertSame([], $entry->active);
        $result = $this->phases->runner()->resolve($launch, self::MAIN_STATE);
        $this->assertTrue($result->outcomeOf(101)?->isPass());

        $bonus = $this->phases->runner()->enter($launch, self::BONUS_STATE, StepMode::Bonus);
        $this->assertSame([101], $bonus->participants);
        $this->assertSame([101], $bonus->active);
    }
}
