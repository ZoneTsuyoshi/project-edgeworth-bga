<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * カード（`name_id`）から効果の定義を引く入口。本番は `CardEffectCatalog`、テストは配列で差し替える。
 */
interface EffectCatalog
{
    public function specFor(string $cardKey): ?EffectSpec;
}
