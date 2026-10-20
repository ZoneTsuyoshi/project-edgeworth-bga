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
use Bga\Games\projectedgeworth\Flow\Action\LinkCommit;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;

final class LinkPhaseTest extends ActionTestCase
{
    private const MAIN_STATE = 81;
    private const BONUS_STATE = 82;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startGame(3);
        foreach ($this->ids as $id) {
            $this->setMaya($id, 40);
        }
    }

    /** @return array<string, mixed> */
    private function link(int $cell, int $satId, array $sell = []): array
    {
        return ['op' => 'link', 'cell' => $cell, 'sat' => $satId, 'sell' => $sell];
    }

    private function commitOf(\Bga\Games\projectedgeworth\Flow\Action\StepResult $result): LinkCommit
    {
        $commit = $result->extra['commit'];
        $this->assertInstanceOf(LinkCommit::class, $commit);
        return $commit;
    }

    /** 衛星を cell に置く（稼働中、向きは SE） */
    private function place(int $playerId, int $index, int $cell, Direction $dir = Direction::SE): int
    {
        $sat = $this->sat($playerId, $index);
        $this->activate($sat->id, $cell, $dir);
        return $sat->id;
    }

    /** 他のキューブを先に置いておく（コミット済み） */
    private function cube(int $playerId, int $cell): void
    {
        $this->phases->services()->links->commit([['playerId' => $playerId, 'cell' => $cell]], 1, $this->globals->incCommitSeq());
    }

    public function testMainTargetsOnlyTheCellDirectlyBelow(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $sat = $this->place(101, 0, $sea);
        $candidates = $this->phases->link()->candidates(101, StepMode::Main);
        $this->assertCount(1, $candidates);
        $this->assertSame($sea, $candidates[0]->cell);
        $this->assertSame($sat, $candidates[0]->options[0]->satellite->id);
    }

    public function testAreaCostIsTwoForSeaAndFiveForLand(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $land = $this->cellOf(AreaType::LAND);
        $a = $this->place(101, 0, $sea);
        $b = $this->place(101, 1, $land);
        $link = $this->phases->link();
        $this->assertSame(2, $link->cost(101, $this->phases->services()->satellites->get($a), $sea)->total());
        $this->assertSame(5, $link->cost(101, $this->phases->services()->satellites->get($b), $land)->total());
    }

    public function testBonusAddsTheCellsInFrontAndBehindWithoutReflection(): void
    {
        $middle = 2 * 12 + 5;
        $sat = $this->place(101, 0, $middle, Direction::SE);
        $link = $this->phases->link();
        $satellite = $this->phases->services()->satellites->get($sat);
        $this->assertSame([$middle], $link->targetCells(101, $satellite, StepMode::Main));
        $this->assertSame([16, 29, 41], $link->targetCells(101, $satellite, StepMode::Bonus));

        // 最上段で上向き: 前方は反射後の区域ではなく、存在しない。後方だけが加わる
        $top = $this->place(102, 0, 5, Direction::NE);
        $topSat = $this->phases->services()->satellites->get($top);
        $this->assertSame([5, 16], $link->targetCells(102, $topSat, StepMode::Bonus));
    }

    public function testLeftAndRightEdgesAreConnectedForTheBonus(): void
    {
        // 行 3（奇数行は 1 つ右にずれる）の最右列で右向き（SE）: 前方は行 4 の左端（col 0）
        $rightEdge = 3 * 12 + 11;
        $sat = $this->place(101, 0, $rightEdge, Direction::SE);
        $cells = $this->phases->link()->targetCells(
            101,
            $this->phases->services()->satellites->get($sat),
            StepMode::Bonus,
        );
        $this->assertSame([2 * 12 + 11, $rightEdge, 4 * 12 + 0], $cells);
    }

    public function testOwnCubeAndOwnLaunchBaseCellsAreExcluded(): void
    {
        $sea = $this->cellOf(AreaType::SEA, 0);
        $land = $this->cellOf(AreaType::LAND, 0);
        $this->place(101, 0, $sea);
        $this->place(101, 1, $land);
        $this->pad(101, $land);
        $this->cube(101, $sea);
        $this->assertSame([], $this->phases->link()->candidates(101, StepMode::Main));
    }

    public function testAnotherPlayersLaunchBaseCanBeLinkedAndItsOwnerIsPaidThePioneerCost(): void
    {
        $land = $this->cellOf(AreaType::LAND);
        $sat = $this->place(101, 0, $land);
        $this->pad(102, $land);
        $cost = $this->phases->link()->cost(101, $this->phases->services()->satellites->get($sat), $land);
        $this->assertSame([['to' => 102, 'amount' => 3]], $cost->pioneer);
        $this->assertSame(8, $cost->total());

        $before102 = $this->maya(102);
        $this->phases->link()->resolve(StepMode::Main, [101 => $this->link($land, $sat)]);
        $this->assertSame(32, $this->maya(101));
        $this->assertSame($before102 + 3, $this->maya(102));
        // 発射拠点は左上（slot 0）を占める（作者回答 2026-10-20）: 最初のキューブは右上（slot 1）
        $this->assertSame(1, $this->phases->services()->links->inCell($land)[0]->slot);
    }

    public function testSimultaneousLinksOnALaunchBaseCellStackOnSlotOneAndALaterOneSkipsTheStack(): void
    {
        $land = $this->cellOf(AreaType::LAND);
        $a = $this->place(101, 0, $land);
        $b = $this->place(102, 0, $land);
        $this->pad(103, $land);
        // 103 の発射拠点がある区域に 101・102 が同時に確立: 重ねて slot 1。発射拠点の持ち主 103 に先駆コスト 3 ずつ
        $this->phases->link()->resolve(StepMode::Main, [101 => $this->link($land, $a), 102 => $this->link($land, $b)]);
        $slots = array_map(static fn($l): int => $l->slot, $this->phases->services()->links->inCell($land));
        $this->assertSame([1, 1], $slots);
        $this->assertSame(40 - 5 - 3, $this->maya(101));
        $this->assertSame(40 + 6, $this->maya(103));
    }

    public function testPioneerCostIsThreePerOtherPlayerWithACube(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $sat = $this->place(103, 0, $sea);
        $this->cube(101, $sea);
        $this->cube(102, $sea);
        $cost = $this->phases->link()->cost(103, $this->phases->services()->satellites->get($sat), $sea);
        $this->assertSame([['to' => 101, 'amount' => 3], ['to' => 102, 'amount' => 3]], $cost->pioneer);
        $this->assertSame(8, $cost->total());
    }

    public function testReductionComesOffTheAreaCostFirstThenThePioneerCostAndFloorsAtZero(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $sat = $this->phases->services()->satellites->get($this->place(103, 0, $sea));
        $this->cube(101, $sea);
        $this->cube(102, $sea);
        $link = $this->phases->link();

        $this->hooks->linkReduction = 1;
        $cost = $link->cost(103, $sat, $sea);
        $this->assertSame(1, $cost->areaCost);
        $this->assertSame(7, $cost->total());

        $this->hooks->linkReduction = 4;
        $cost = $link->cost(103, $sat, $sea);
        $this->assertSame(0, $cost->areaCost);
        $this->assertSame([['to' => 101, 'amount' => 1], ['to' => 102, 'amount' => 3]], $cost->pioneer);

        $this->hooks->linkReduction = 99;
        $this->assertSame(0, $link->cost(103, $sat, $sea)->total());
    }

    public function testSpecialLinkAddsFiveAfterTheFloor(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $sat = $this->phases->services()->satellites->get($this->place(101, 0, $sea));
        $link = $this->phases->link();
        $this->assertSame(7, $link->cost(101, $sat, $sea, true)->total());
        $this->hooks->linkReduction = 99;
        $this->assertSame(5, $link->cost(101, $sat, $sea, true)->total());
        $this->assertSame(0, $link->cost(101, $sat, $sea)->total());
    }

    public function testPioneerWaiverRemovesOnlyThePioneerCost(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $sat = $this->phases->services()->satellites->get($this->place(103, 0, $sea));
        $this->cube(101, $sea);
        $this->hooks->pioneerWaived = true;
        $cost = $this->phases->link()->cost(103, $sat, $sea);
        $this->assertSame([], $cost->pioneer);
        $this->assertSame(2, $cost->total());
    }

    public function testExtraTargetsAreUnitedAndForbiddenSatellitesHaveNone(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $land = $this->cellOf(AreaType::LAND);
        $sat = $this->place(101, 0, $sea);
        $this->hooks->extraTargets = [$land];
        $cells = array_map(
            fn($c): int => $c->cell,
            $this->phases->link()->candidates(101, StepMode::Main),
        );
        $this->assertEqualsCanonicalizing([$sea, $land], $cells);

        $this->hooks->forbidden[ForbiddenAction::Link->value] = [$sat];
        $this->assertSame([], $this->phases->link()->candidates(101, StepMode::Main));
    }

    public function testSatelliteChoiceWhenSeveralOwnSatellitesReachTheCell(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $this->place(101, 0, $sea);
        $b = $this->place(101, 1, $sea);
        $candidate = $this->phases->link()->candidates(101, StepMode::Main)[0];
        $this->assertCount(2, $candidate->options);
        $result = $this->phases->link()->resolve(StepMode::Main, [101 => $this->link($sea, $b)]);
        $this->assertSame($b, $result->outcomeOf(101)?->details['sat']);
    }

    public function testSimultaneousLinksStackWithNoPioneerCostBetweenThem(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $sats = [101 => $this->place(101, 0, $sea), 102 => $this->place(102, 0, $sea), 103 => $this->place(103, 0, $sea)];
        $choices = [];
        foreach ($sats as $playerId => $satId) {
            $choices[$playerId] = $this->link($sea, $satId);
        }
        $result = $this->phases->link()->resolve(StepMode::Main, $choices);

        $links = $this->phases->services()->links->inCell($sea);
        $this->assertCount(3, $links);
        $this->assertSame([0, 0, 0], array_map(static fn($l): int => $l->slot, $links));
        $this->assertSame([0, 1, 2], array_map(static fn($l): int => $l->level, $links));
        $this->assertSame([101, 102, 103], array_map(static fn($l): int => $l->playerId, $links));
        $this->assertSame(1, count(array_unique(array_map(static fn($l): int => $l->commit, $links))));
        foreach ($this->ids as $id) {
            $this->assertSame(38, $this->maya($id));
        }
        $this->assertSame([], $this->commitOf($result)->pioneerReceived);
    }

    public function testLaterLinkSkipsAsManySlotsAsThereAreCubesAndPaysEveryone(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $this->cube(101, $sea);
        $this->cube(102, $sea);
        // cube() は別々のコミット: slot 0・1。3 番目はキューブ 2 個分飛ばして左下（slot 2）
        $sat = $this->place(103, 0, $sea);
        $this->phases->link()->resolve(StepMode::Main, [103 => $this->link($sea, $sat)]);
        $links = $this->phases->services()->links->inCell($sea);
        $this->assertSame([0, 1, 2], array_map(static fn($l): int => $l->slot, $links));
        $this->assertSame(40 - 8, $this->maya(103));
        $this->assertSame(43, $this->maya(101));
        $this->assertSame(43, $this->maya(102));
    }

    public function testStackedPairThenThirdPlayerGoesToLowerLeft(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $a = $this->place(101, 0, $sea);
        $b = $this->place(102, 0, $sea);
        $c = $this->place(103, 0, $sea);
        $this->phases->link()->resolve(StepMode::Main, [101 => $this->link($sea, $a), 102 => $this->link($sea, $b)]);
        $this->phases->link()->resolve(StepMode::Bonus, [103 => $this->link($sea, $c)]);

        $slots = array_map(static fn($l): int => $l->slot, $this->phases->services()->links->inCell($sea));
        $this->assertSame([0, 0, 2], $slots);
        // 後から通信確立した 103 は、先に置いた 2 人に先駆コスト 3 ずつ
        $this->assertSame(40 - 2 - 6, $this->maya(103));
        $this->assertSame(40 - 2 + 3, $this->maya(101));
    }

    public function testPioneerReceiptsCannotFundPaymentsInTheSameCommit(): void
    {
        // 101 は X に、102 は Y に既にキューブがある。ボーナスで 101 が Y を、102 が X を通信確立する（互いに先駆コスト）
        $x = $this->cellOf(AreaType::SEA, 0);
        $y = $this->cellOf(AreaType::SEA, 1);
        $sat101 = $this->place(101, 0, $y);
        $sat102 = $this->place(102, 0, $x);
        $this->cube(101, $x);
        $this->cube(102, $y);
        // コストは区域 2 + 先駆 3 = 5。所持 5 ちょうどなら払え、互いに 3 を受け取る
        $this->setMaya(101, 5);
        $this->setMaya(102, 5);
        $result = $this->phases->link()->resolve(StepMode::Bonus, [101 => $this->link($y, $sat101), 102 => $this->link($x, $sat102)]);
        $this->assertSame(3, $this->maya(101));
        $this->assertSame(3, $this->maya(102));
        $this->assertSame([101 => 3, 102 => 3], $this->commitOf($result)->pioneerReceived);

        // 所持 4 では、相手から 3 を受け取る予定があっても払えない（処理前の所持金で判定）
        $this->db->execute('DELETE FROM `link`');
        $this->setMaya(101, 4);
        $this->setMaya(102, 4);
        $this->cube(101, $x);
        $this->cube(102, $y);
        $result = $this->phases->link()->resolve(StepMode::Bonus, [101 => $this->link($y, $sat101), 102 => $this->link($x, $sat102)]);
        $this->assertTrue($result->outcomeOf(101)?->paymentFailed);
        $this->assertTrue($result->outcomeOf(102)?->paymentFailed);
        $this->assertSame(4, $this->maya(101));
        $this->assertSame(4, $this->maya(102));
        $this->assertSame(2, $this->phases->services()->links->countInCell($x) + $this->phases->services()->links->countInCell($y));
    }

    public function testValidationRejectsUnpayableAndAcceptsSellingToCover(): void
    {
        $land = $this->cellOf(AreaType::LAND);
        $sat = $this->place(101, 0, $land);
        $this->setHand(101, [1, 2]);
        $this->setMaya(101, 0);
        $link = $this->phases->link();
        // 陸 5。手札 2 枚の売却は 6 → 払える。1 枚（3）では足りない
        $link->validate(101, StepMode::Main, $this->link($land, $sat, [1, 2]));
        $this->assertTrue($link->candidates(101, StepMode::Main)[0]->isSelectable());
        try {
            $link->validate(101, StepMode::Main, $this->link($land, $sat, [1]));
            $this->fail('expected RuleViolation');
        } catch (RuleViolation) {
            $this->addToAssertionCount(1);
        }
        $this->phases->link()->resolve(StepMode::Main, [101 => $this->link($land, $sat, [1, 2])]);
        $this->assertSame(1, $this->maya(101));
        $this->assertSame([], $this->handOf(101));
    }

    public function testInvalidTargetsAreRejected(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $land = $this->cellOf(AreaType::LAND);
        $sat = $this->place(101, 0, $sea);
        $foreign = $this->place(102, 0, $sea);
        $link = $this->phases->link();
        foreach ([$this->link($land, $sat), $this->link($sea, $foreign), $this->link($sea, 9999), ['op' => 'link'], ['op' => 'x']] as $data) {
            try {
                $link->validate(101, StepMode::Main, $data);
                $this->fail('expected RuleViolation');
            } catch (RuleViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testNoCandidatesOnceAllSixteenCubesAreUsed(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $this->place(101, 0, $sea);
        $cells = array_slice(array_filter($this->layout->cellIds(), fn(int $c): bool => $c !== $sea), 0, 16);
        foreach ($cells as $cell) {
            $this->cube(101, $cell);
        }
        $this->assertSame(0, $this->phases->link()->remainingCubes(101));
        $this->assertSame([], $this->phases->link()->candidates(101, StepMode::Main));
        $this->assertFalse($this->phases->link()->canAct(101, StepMode::Main));
    }

    public function testPassAbandonsTheCubeAndCostsNothing(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $this->place(101, 0, $sea);
        $result = $this->phases->link()->resolve(StepMode::Main, [101 => ['op' => 'pass']]);
        $this->assertTrue($result->outcomeOf(101)?->isPass());
        $this->assertSame(0, $this->phases->services()->links->countFor(101));
        $this->assertSame(40, $this->maya(101));
        $this->assertNull($this->commitOf($result)->commitNo);
    }

    public function testStepFlowEndToEnd(): void
    {
        $sea = $this->cellOf(AreaType::SEA);
        $sat = $this->place(101, 0, $sea);
        $link = $this->phases->link();
        $runner = $this->phases->runner();
        $entry = $runner->enter($link, self::MAIN_STATE);
        $this->assertSame([101], $entry->active);
        $this->assertSame([102, 103], $entry->autoConfirmed);
        // 102・103 は入場時に自動で確定済みなので、101 の確定で全員そろう
        $this->assertTrue($runner->confirm($link, self::MAIN_STATE, 101, $this->link($sea, $sat)));
        $result = $runner->resolve($link, self::MAIN_STATE);
        $this->assertSame('link', $result->outcomeOf(101)?->op);
        $this->assertSame(1, $this->globals->commitSeq());

        $this->globals->setBonusHolders([4 => [101, 102]]);
        $bonus = $runner->enter($link, self::BONUS_STATE, StepMode::Bonus);
        $this->assertSame([101, 102], $bonus->participants);
        $this->assertSame([102], $bonus->autoConfirmed);
    }
}
