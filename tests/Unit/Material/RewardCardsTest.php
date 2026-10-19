<?php

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Material;

use Bga\Games\ProjectEdgeworth\Material\RewardCards;
use Bga\Games\ProjectEdgeworth\Material\RewardClass;
use Bga\Games\ProjectEdgeworth\Material\RewardMark;
use PHPUnit\Framework\TestCase;

final class RewardCardsTest extends TestCase
{
    public function testHas24FacesWithContiguousIds(): void
    {
        $expected = [];
        for ($i = 1; $i <= 24; $i++) {
            $expected[] = sprintf('T%03d', $i);
        }

        self::assertSame($expected, array_keys(RewardCards::all()));
    }

    public function testEveryFaceAwardsFamePointsByRank(): void
    {
        foreach (RewardCards::all() as $id => $card) {
            self::assertSame([18, 12, 6, 0], $card->points, $id);
        }
    }

    public function testMarkAndClassBreakdownMatchesSpec002(): void
    {
        $marks = [];
        $classes = [];
        foreach (RewardCards::all() as $id => $card) {
            $marks[$card->mark->value][] = $id;
            $classes[$card->class->value][] = $id;
        }

        self::assertSame(['T001', 'T002'], $marks['STAR1']);
        self::assertSame(['T009', 'T010', 'T011'], $marks['STAR2']);
        self::assertSame(['T017', 'T018', 'T019', 'T020', 'T021', 'T022', 'T023', 'T024'], $marks['ADVANCED_ONLY']);
        self::assertSame(['T003', 'T004', 'T005', 'T006', 'T007', 'T008', 'T012', 'T013', 'T014', 'T015', 'T016'], $marks['NONE']);
        self::assertSame(['T009', 'T010', 'T011', 'T012', 'T013', 'T014', 'T015', 'T016'], $classes['OTHER']);
        self::assertCount(16, $classes['CUBE']);
    }

    public function testSpotChecksAgainstSpec002(): void
    {
        $t001 = RewardCards::get('T001');
        self::assertSame('海上覇権', $t001->nameJa);
        self::assertSame('Sea Hegemony', $t001->nameEn);
        self::assertSame('sea_hegemony', $t001->nameId);
        self::assertSame(RewardClass::Cube, $t001->class);
        self::assertSame(RewardMark::Star1, $t001->mark);

        $t024 = RewardCards::get('T024');
        self::assertSame('evergreen_hegemony', $t024->nameId);
        self::assertSame('Number of linked [Land] areas adjacent to a Reef area', $t024->compareEn);
        self::assertSame(RewardMark::AdvancedOnly, $t024->mark);
    }
}
