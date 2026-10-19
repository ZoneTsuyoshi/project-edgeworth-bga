<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts\Modifier;

use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Parts\ModifierPart;
use Bga\Games\ProjectEdgeworth\Effects\Query\QueryContext;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;

/**
 * M11 `FUEL_CONSUME_MOD`: 循環フェイズの移動の燃料消費（基本 1）を 0 にする（P079・P080）。このカードのある衛星だけに効く。
 * 地域（K06）と CREW・DEVICE・SOFTWARE の揃い（K03）は `EffectSpec::$conditions`。問い合わせの `cell` は移動後の位置
 * （燃料消費の時点で直下にある地域で判定。FAQ 行 33）。
 */
final class FuelConsumeMod implements ModifierPart
{
    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        return $query->satelliteId !== null && $query->satelliteId === $host->satelliteId;
    }

    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
    {
        return 0;
    }
}
