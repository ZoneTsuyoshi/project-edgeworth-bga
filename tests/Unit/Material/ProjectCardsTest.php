<?php

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Material;

use Bga\Games\ProjectEdgeworth\Material\ActiveCondition;
use Bga\Games\ProjectEdgeworth\Material\EffectKind;
use Bga\Games\ProjectEdgeworth\Material\EffectTiming;
use Bga\Games\ProjectEdgeworth\Material\ProjectCards;
use Bga\Games\ProjectEdgeworth\Material\ProjectType;
use PHPUnit\Framework\TestCase;

final class ProjectCardsTest extends TestCase
{
    public function testHas99CardsWithContiguousIds(): void
    {
        $expected = [];
        for ($i = 1; $i <= 99; $i++) {
            $expected[] = sprintf('P%03d', $i);
        }

        self::assertSame($expected, array_keys(ProjectCards::all()));
    }

    public function testTypeFollowsIdRange(): void
    {
        foreach (ProjectCards::all() as $id => $card) {
            $n = (int) substr($id, 1);
            $type = $n <= 33 ? ProjectType::Crew : ($n <= 66 ? ProjectType::Device : ProjectType::Software);
            self::assertSame($type, $card->type, $id);
        }
    }

    public function testNameIdsAreUniqueSnakeCaseAndTextsArePresent(): void
    {
        $nameIds = [];
        foreach (ProjectCards::all() as $id => $card) {
            self::assertSame($id, $card->id);
            self::assertMatchesRegularExpression('/^[a-z0-9]+(_[a-z0-9]+)*$/', $card->nameId, $id);
            self::assertNotSame('', $card->nameJa, $id);
            self::assertNotSame('', $card->nameEn, $id);
            self::assertNotSame('', $card->textJa, $id);
            self::assertNotSame('', $card->textEn, $id);
            self::assertGreaterThanOrEqual(1, $card->fuelSlots, $id);
            $nameIds[] = $card->nameId;
        }

        self::assertCount(99, array_unique($nameIds));
    }

    public function testSpotChecksAgainstSpec002(): void
    {
        $p001 = ProjectCards::get('P001');
        self::assertSame('航空技師', $p001->nameJa);
        self::assertSame('Aviation Engineer', $p001->nameEn);
        self::assertSame('aviation_engineer', $p001->nameId);
        self::assertSame([5, 2, 2], [$p001->cost, $p001->fame, $p001->fuelSlots]);
        self::assertSame(EffectTiming::PlanningPhase, $p001->timing);
        self::assertSame(EffectKind::Activated, $p001->effectKind);
        self::assertSame(ActiveCondition::ActiveOnly, $p001->activeCondition);

        $p026 = ProjectCards::get('P026');
        self::assertSame('french_bulldog', $p026->nameId);
        self::assertSame([10, 6, 4], [$p026->cost, $p026->fame, $p026->fuelSlots]);
        self::assertSame(EffectKind::OnDeploy, $p026->effectKind);
        self::assertSame(ActiveCondition::Any, $p026->activeCondition);

        $p042 = ProjectCards::get('P042');
        self::assertSame([15, 2, 3], [$p042->cost, $p042->fame, $p042->fuelSlots]);
        self::assertSame(EffectKind::OnLaunch, $p042->effectKind);

        $p066 = ProjectCards::get('P066');
        self::assertSame('signal_blocker', $p066->nameId);
        self::assertSame([10, 8, 2], [$p066->cost, $p066->fame, $p066->fuelSlots]);
        self::assertSame(EffectTiming::Linked, $p066->timing);

        // P077 は凡例に無い ANYTIME（いつでも）
        self::assertSame(EffectTiming::Anytime, ProjectCards::get('P077')->timing);
        // P098 はエラッタで循環フェイズ中が正（印刷は企画フェイズのアイコン）
        self::assertSame(EffectTiming::OrbitPhase, ProjectCards::get('P098')->timing);
        // 印刷では P048 が可変軌道装置、P049 が太陽電池パネル（マスター・FAQ とは逆）
        self::assertSame('variable_orbit_unit', ProjectCards::get('P048')->nameId);
        self::assertSame('solar_panel', ProjectCards::get('P049')->nameId);
    }

    public function testByNameIdAndUnknownId(): void
    {
        self::assertSame('P099', ProjectCards::byNameId('flyby_link')->id);

        $this->expectException(\OutOfBoundsException::class);
        ProjectCards::get('P100');
    }

    public function testAggregateTotalsMatchSpec002(): void
    {
        $cost = $fame = $fuel = 0;
        foreach (ProjectCards::all() as $card) {
            $cost += $card->cost;
            $fame += $card->fame;
            $fuel += $card->fuelSlots;
        }
        // 002 §5 の表から集計した値（定義の書き換えを検知するための控え）
        self::assertSame(698, $cost);
        self::assertSame(214, $fame);
        self::assertSame(231, $fuel);
    }
}
