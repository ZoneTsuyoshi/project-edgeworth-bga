<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * アクションカード 1 枚の定義（002 §9）。文面の改行は "\n"。
 */
final readonly class ActionCardDefinition
{
    public function __construct(
        public string $id,
        public string $numeral,
        public string $nameJa,
        public string $nameEn,
        public string $nameId,
        public ActionKind $kind,
        public string $mainJa,
        public string $mainEn,
        public string $bonusJa,
        public string $bonusEn,
    ) {
    }
}
