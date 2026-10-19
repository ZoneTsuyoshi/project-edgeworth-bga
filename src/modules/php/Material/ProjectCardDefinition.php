<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * プロジェクトカード 1 枚の定義（002 §4.1）。効果は `nameId` で識別する（002 §3.1。011 の EffectSpec はこの `nameId` で引く）。
 */
final readonly class ProjectCardDefinition
{
    public function __construct(
        public string $id,
        public string $nameJa,
        public string $nameEn,
        public string $nameId,
        public ProjectType $type,
        public int $cost,
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
