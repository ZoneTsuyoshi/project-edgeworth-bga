<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Action;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Flow\Action\ProposalCounts;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;

final class ProposalPhaseTest extends ActionTestCase
{
    private const KEEP_STATE = 91;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startGame(3);
        // 102 が V を選んだ（ボーナス = メインの代わりに 5 枚引いて 2 枚残す）
        $this->globals->setBonusHolders([5 => [102]]);
    }

    public function testMainDrawsTwoKeepsOneAndBonusDrawsFiveKeepsTwo(): void
    {
        $proposal = $this->phases->proposal();
        $this->assertEquals(new ProposalCounts(2, 1), $proposal->counts(101, false));
        $this->assertEquals(new ProposalCounts(5, 2), $proposal->counts(102, true));

        $drawn = $proposal->drawAll();
        $this->assertCount(2, $drawn[101]);
        $this->assertCount(5, $drawn[102]);
        $this->assertCount(2, $drawn[103]);
        $this->assertSame(1, $proposal->keepCount(101));
        $this->assertSame(2, $proposal->keepCount(102));
        $this->assertSame($drawn[102], $proposal->drawn(102));
    }

    public function testCardsAreDealtInSeatOrderFromTheTopOfTheDeck(): void
    {
        $cards = $this->phases->services()->cards;
        $probe = $cards->peekTop(CardLocation::Deck);
        $this->assertNotNull($probe);
        $drawn = $this->phases->proposal()->drawAll();
        $top = array_merge($drawn[101], $drawn[102], $drawn[103]);
        $this->assertSame(9, count(array_unique($top)));
        $this->assertSame($probe, $drawn[101][0]);
    }

    public function testKeepGoesToHandAndTheRestIsDiscarded(): void
    {
        $proposal = $this->phases->proposal();
        $handBefore = count($this->handOf(101));
        $discardBefore = $this->phases->services()->cards->countIn(CardLocation::Discard);
        $drawn = $proposal->drawAll();

        $runner = $this->phases->runner();
        $entry = $runner->enter($proposal, self::KEEP_STATE);
        $this->assertSame([101, 102, 103], $entry->active);
        $runner->confirm($proposal, self::KEEP_STATE, 101, ['keep' => [$drawn[101][1]]]);
        $runner->confirm($proposal, self::KEEP_STATE, 102, ['keep' => [$drawn[102][0], $drawn[102][4]]]);
        // 103 は確定しなくても、引いた順の先頭から残す（zombie の既定）
        $result = $runner->resolve($proposal, self::KEEP_STATE);

        $this->assertCount($handBefore + 1, $this->handOf(101));
        $this->assertContains($drawn[101][1], $this->handOf(101));
        $this->assertCount(2, array_intersect($this->handOf(102), $drawn[102]));
        $this->assertContains($drawn[103][0], $this->handOf(103));
        $this->assertSame([$drawn[101][0]], $result->outcomeOf(101)?->details['discarded']);
        $this->assertSame(3, count($result->outcomeOf(102)?->details['discarded']));
        $this->assertTrue($result->outcomeOf(102)?->details['bonus']);
        $this->assertSame($discardBefore + 1 + 3 + 1, $this->phases->services()->cards->countIn(CardLocation::Discard));
        foreach ($this->ids as $id) {
            $this->assertSame([], $proposal->drawn($id));
        }
    }

    public function testChoiceMustBeExactlyTheKeepCountFromTheDrawnCards(): void
    {
        $proposal = $this->phases->proposal();
        $drawn = $proposal->drawAll();
        $bad = [
            ['keep' => []],
            ['keep' => $drawn[101]],
            ['keep' => [$drawn[101][0], $drawn[101][0]]],
            ['keep' => [$drawn[102][0]]],
            ['keep' => 'x'],
            [],
        ];
        foreach ($bad as $data) {
            try {
                $proposal->validate(101, StepMode::Main, $data);
                $this->fail('expected RuleViolation');
            } catch (RuleViolation) {
                $this->addToAssertionCount(1);
            }
        }
        $proposal->validate(101, StepMode::Main, ['keep' => [$drawn[101][0]]]);
    }

    public function testHookChangesDrawAndKeepCounts(): void
    {
        $this->hooks->mainCounts = new ProposalCounts(3, 2);
        $this->hooks->bonusCounts = new ProposalCounts(7, 4);
        $drawn = $this->phases->proposal()->drawAll();
        $this->assertCount(3, $drawn[101]);
        $this->assertCount(7, $drawn[102]);
        $this->assertSame(2, $this->phases->proposal()->keepCount(101));
        $this->assertSame(4, $this->phases->proposal()->keepCount(102));
    }

    public function testKeepCountNeverExceedsWhatWasDrawnWhenThePilesRunOut(): void
    {
        // 山札と捨て札を 1 枚だけにする
        $this->db->execute("UPDATE `project_card` SET `card_location` = 'discard', `card_location_arg` = 0 WHERE `card_location` = 'deck'");
        $this->db->execute(
            "UPDATE `project_card` SET `card_location` = 'hand', `card_location_arg` = 101 WHERE `card_location` = 'discard'",
        );
        $this->db->execute("UPDATE `project_card` SET `card_location` = 'deck', `card_location_arg` = 0, `card_order` = 0 WHERE `card_id` = 50");
        $drawn = $this->phases->proposal()->drawAll();
        $this->assertSame(1, count($drawn[101]) + count($drawn[102]) + count($drawn[103]));
        $this->assertSame(1, $this->phases->proposal()->keepCount(101));
        $this->assertSame(0, $this->phases->proposal()->keepCount(102));
        $this->assertFalse($this->phases->proposal()->canAct(102, StepMode::Main));
    }

    public function testPlayersWhoDrewNothingAreConfirmedAutomatically(): void
    {
        $this->db->execute("UPDATE `project_card` SET `card_location` = 'hand', `card_location_arg` = 101 WHERE `card_location` <> 'hand'");
        $proposal = $this->phases->proposal();
        $this->assertSame([], array_merge(...array_values($proposal->drawAll())));
        $entry = $this->phases->runner()->enter($proposal, self::KEEP_STATE);
        $this->assertSame([], $entry->active);
        $this->assertSame([101, 102, 103], $entry->autoConfirmed);
        $result = $this->phases->runner()->resolve($proposal, self::KEEP_STATE);
        $this->assertTrue($result->outcomeOf(102)?->isPass());
    }
}
