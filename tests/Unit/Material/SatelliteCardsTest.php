<?php

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Material;

use Bga\Games\projectedgeworth\Material\ActiveCondition;
use Bga\Games\projectedgeworth\Material\EffectKind;
use Bga\Games\projectedgeworth\Material\EffectTiming;
use Bga\Games\projectedgeworth\Material\SatelliteCards;
use Bga\Games\projectedgeworth\Material\SatelliteColor;
use Bga\Games\projectedgeworth\Material\SatellitePlan;
use Bga\Games\projectedgeworth\Material\SatelliteShape;
use PHPUnit\Framework\TestCase;

final class SatelliteCardsTest extends TestCase
{
    public function testHas40FacesWithExpectedIds(): void
    {
        $expected = [];
        for ($n = 1; $n <= 5; $n++) {
            foreach (['B', 'G', 'R', 'Y'] as $c) {
                $expected[] = sprintf('R%03d%s', $n, $c);
            }
        }
        for ($n = 6; $n <= 25; $n++) {
            $expected[] = sprintf('R%03d', $n);
        }

        self::assertSame($expected, array_keys(SatelliteCards::all()));
    }

    public function testMoonFacesCoverEveryShapeAndColorWithSharedValues(): void
    {
        $combos = [];
        $byShape = [];
        foreach (SatelliteCards::all() as $card) {
            if ($card->plan !== SatellitePlan::Moon) {
                continue;
            }
            $combos[] = $card->shape->value . '/' . $card->color->value;
            // 色違いは効果・コスト・名声が同一
            $key = $card->shape->value;
            $values = [$card->nameId, $card->launchCost, $card->fame, $card->textJa, $card->textEn];
            $byShape[$key] ??= $values;
            self::assertSame($byShape[$key], $values, $card->id);
            self::assertSame(ActiveCondition::Any, $card->activeCondition, $card->id);
            self::assertSame(EffectTiming::Launched, $card->timing, $card->id);
            self::assertSame(EffectKind::OnLaunch, $card->effectKind, $card->id);
        }

        self::assertCount(20, array_unique($combos));
        self::assertSame(['HAMMER', 'RETRO', 'SCALLOP', 'CONCORDE', 'STEALTH'], array_keys($byShape));
    }

    public function testPlanFacesPairPlanWithColorAndShapeInOrder(): void
    {
        $colorOfPlan = [
            'PHOBOS' => SatelliteColor::Blue,
            'DEIMOS' => SatelliteColor::Green,
            'EUROPA' => SatelliteColor::Red,
            'GANYMEDE' => SatelliteColor::Yellow,
        ];
        $shapes = SatelliteShape::cases();
        $n = 6;
        foreach ($colorOfPlan as $plan => $color) {
            foreach ($shapes as $shape) {
                $card = SatelliteCards::get(sprintf('R%03d', $n++));
                self::assertSame($plan, $card->plan->value);
                self::assertSame($color, $card->color);
                self::assertSame($shape, $card->shape);
                self::assertSame(strtolower($shape->value . '_' . $plan), $card->nameId);
                self::assertSame(ActiveCondition::ActiveOnly, $card->activeCondition);
            }
        }
    }

    public function testEveryFaceHasTwoFuelSlotsAndTexts(): void
    {
        foreach (SatelliteCards::all() as $id => $card) {
            self::assertSame(2, $card->fuelSlots, $id);
            self::assertNotSame('', $card->textJa, $id);
            self::assertNotSame('', $card->textEn, $id);
        }
    }

    public function testSpotChecksAgainstSpec002(): void
    {
        $r005 = SatelliteCards::get('R005Y');
        self::assertSame('stealth_moon', $r005->nameId);
        self::assertSame(SatelliteColor::Yellow, $r005->color);
        self::assertSame([30, 15], [$r005->launchCost, $r005->fame]);

        $r019 = SatelliteCards::get('R019');
        self::assertSame('concorde_europa', $r019->nameId);
        self::assertSame(EffectTiming::LaunchedOrTurned, $r019->timing);
        self::assertSame([30, 10], [$r019->launchCost, $r019->fame]);

        $r009 = SatelliteCards::get('R009');
        self::assertSame(EffectTiming::Anytime, $r009->timing);

        $r020 = SatelliteCards::get('R020');
        self::assertSame([35, 10], [$r020->launchCost, $r020->fame]);

        $r023 = SatelliteCards::get('R023');
        self::assertSame('scallop_ganymede', $r023->nameId);
        self::assertSame(EffectTiming::Linked, $r023->timing);
        self::assertSame(EffectKind::Activated, $r023->effectKind);
        self::assertSame([20, 10], [$r023->launchCost, $r023->fame]);
    }
}
