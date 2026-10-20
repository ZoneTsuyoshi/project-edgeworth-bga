<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 衛星カード 1 面の定義（002 §4.2）。MOON 面は色ごとに別の面（R001B など）で、`nameId` は同じ。
 */
final readonly class SatelliteCardDefinition
{
    public function __construct(
        public string $id,
        public SatellitePlan $plan,
        public SatelliteColor $color,
        public SatelliteShape $shape,
        public string $nameId,
        public int $launchCost,
        public int $fame,
        public int $fuelSlots,
        public EffectTiming $timing,
        public EffectKind $effectKind,
        public ActiveCondition $activeCondition,
        public string $textJa,
        public string $textEn,
    ) {
    }
}
