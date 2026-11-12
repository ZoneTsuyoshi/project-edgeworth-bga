<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Machine;

use Bga\Games\projectedgeworth\Flow\Machine\PublicView;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Material\RewardCards;

/** 褒賞カードの現在値（getAllDatas の playerData[pid].rewardValues と通知 N33）。送られていないとパネルが「–」になる。 */
final class RewardValuesTest extends MachineTestCase
{
    public function testAllTwentyFourFacesHaveValuesInGetAllDatasAndCounters(): void
    {
        $this->startGame();
        $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, [], [StateId::PLANNING_PLOT]);

        $ids = array_keys(RewardCards::all());
        self::assertCount(24, $ids);
        foreach (array_chunk($ids, 2) as $pair) {
            $this->globals->setRewardCards($pair);
            $view = (new PublicView($this->machine->ctx))->build(101);
            foreach ([101, 102] as $playerId) {
                $values = $view['playerData'][$playerId]['rewardValues'];
                self::assertSame($pair, array_keys($values));
                foreach ($values as $value) {
                    self::assertIsInt($value);
                    self::assertGreaterThanOrEqual(0, $value);
                }
            }
            $counters = $this->machine->ctx->counters();
            foreach ([101, 102] as $playerId) {
                self::assertSame($pair, array_keys($counters->args['players'][$playerId]['rewardValues']));
            }
        }
    }

    public function testBudgetAndMayaRewardsFollowCurrentValues(): void
    {
        $this->startGame();
        $this->walker->autoPlay(StateId::SETUP_LAUNCH_PAD_NEXT, [], [StateId::PLANNING_PLOT]);
        $this->globals->setRewardCards(['T013', 'T014']);
        $this->playerValues->setMaya(101, 37);

        $view = (new PublicView($this->machine->ctx))->build(102);
        self::assertSame(37, $view['playerData'][101]['rewardValues']['T014']);
        self::assertSame($view['playerData'][101]['budget'], $view['playerData'][101]['rewardValues']['T013']);
    }
}
