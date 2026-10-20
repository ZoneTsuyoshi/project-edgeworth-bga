<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * `CardEffects` を引く `EffectCatalog`。
 */
final class CardEffectCatalog implements EffectCatalog
{
    public function specFor(string $cardKey): ?EffectSpec
    {
        return CardEffects::get($cardKey);
    }
}
