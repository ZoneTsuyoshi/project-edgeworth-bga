<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Flow\Action;

use Bga\Games\projectedgeworth\Board\AreaType;
use Bga\Games\projectedgeworth\Board\BoardFace;
use Bga\Games\projectedgeworth\Board\BoardLayoutBuilder;
use Bga\Games\projectedgeworth\Board\BoardPlacement;
use Bga\Games\projectedgeworth\Effects\Context\StagedLinkRequest;
use Bga\Games\projectedgeworth\Flow\Action\LinkCost;
use Bga\Games\projectedgeworth\Flow\Action\StagedLinkConverter;
use PHPUnit\Framework\TestCase;
use ProjectEdgeworthTests\Support\SeededRandom;

/**
 * 通信確立コストの内訳（001 §7-5、007 §5-4）の組み立てと、ステージへの保存・復元（R11。007 §7-3）。
 */
final class LinkCostTest extends TestCase
{
    public function testAreaCostOnlyWithoutPioneersOrModifiers(): void
    {
        $cost = LinkCost::compute(AreaType::SEA, 0, 0, [], false, false);
        $this->assertSame([2, 0, 0, 2], [$cost->areaCost, $cost->pioneerTotal(), $cost->specialCost, $cost->total()]);
        $this->assertSame(5, LinkCost::compute(AreaType::LAND, 0, 0, [], false, false)->total());
    }

    public function testReductionComesOffTheAreaCostThenThePioneerCostsInSeatOrder(): void
    {
        // 作者回答 2026-10-20（007 未確定 7）: 余りを先駆コストから席順に引く
        $cost = LinkCost::compute(AreaType::LAND, 0, 7, [2, 3], false, false);
        $this->assertSame(0, $cost->areaCost);
        $this->assertSame([['to' => 2, 'amount' => 1], ['to' => 3, 'amount' => 3]], $cost->pioneer);

        $cost = LinkCost::compute(AreaType::LAND, 0, 9, [2, 3], false, false);
        $this->assertSame([['to' => 3, 'amount' => 2]], $cost->pioneer, '払う額が 0 になった相手は内訳から外す');

        $this->assertSame(0, LinkCost::compute(AreaType::LAND, 0, 99, [2, 3], false, false)->total(), '下限 0');
    }

    public function testReefDiscountLowersTheAreaCostBeforeTheReduction(): void
    {
        $cost = LinkCost::compute(AreaType::LAND, 1, 1, [2], false, false);
        $this->assertSame([5, 1, 1, 3, 6], [$cost->baseArea, $cost->reefDiscount, $cost->reduction, $cost->areaCost, $cost->total()]);
    }

    public function testWaivedPioneerCostAndTheSpecialCostAfterTheFloor(): void
    {
        $cost = LinkCost::compute(AreaType::SEA, 0, 0, [2, 3], true, true);
        $this->assertSame([], $cost->pioneer);
        $this->assertSame(7, $cost->total());

        // 特殊コストは下限 0 の後（FAQ 行 74）
        $this->assertSame(5, LinkCost::compute(AreaType::SEA, 0, 99, [], false, true)->total());
    }

    public function testReefDiscountIsZeroWithoutAReefOnTheStandardFace(): void
    {
        $layout = BoardLayoutBuilder::build(BoardFace::STANDARD, BoardPlacement::EARTH, 4, new SeededRandom(1));
        $this->assertSame(0, LinkCost::reefDiscount($layout, [0, 1, 2], 14));
        $this->assertSame(0, LinkCost::reefDiscount($layout, [], 12), '海には割引がない');
    }

    public function testArrayRoundTripKeepsEveryPart(): void
    {
        $cost = LinkCost::compute(AreaType::LAND, 0, 2, [2, 3], false, true);
        $copy = LinkCost::fromArray(json_decode((string) json_encode($cost->toArray()), true));
        $this->assertEquals($cost, $copy);
        $this->assertSame($cost->total(), $copy->total());
    }

    public function testConverterBuildsRequestsFromTheReservedCost(): void
    {
        $special = LinkCost::compute(AreaType::SEA, 0, 0, [], false, true);
        $normal = LinkCost::compute(AreaType::LAND, 0, 0, [2], false, false);
        $requests = StagedLinkConverter::requests([
            new StagedLinkRequest(1, 10, 3, 'P:50', $special->toArray()),
            new StagedLinkRequest(2, 20, 4, 'P:76', $normal->toArray()),
        ]);

        $this->assertCount(2, $requests);
        $this->assertSame([1, 10, 3, true], [$requests[0]->playerId, $requests[0]->satelliteId, $requests[0]->cell, $requests[0]->special]);
        $this->assertSame([2, 20, 4, false], [$requests[1]->playerId, $requests[1]->satelliteId, $requests[1]->cell, $requests[1]->special]);
        $this->assertEquals($normal, $requests[1]->cost);
        $this->assertSame([], $requests[1]->sell, '売却は [Use] の答えで済んでいる');
    }
}
