<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts\Modifier;

use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Parts\ModifierPart;
use Bga\Games\ProjectEdgeworth\Effects\Query\NearMissAdjust;
use Bga\Games\ProjectEdgeworth\Effects\Query\QueryContext;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;

/**
 * M13 `NEAR_MISS_SUPPRESS`: このカードのある衛星をニアミスの対象から除く（P094。条件 K03）。同じ区域の他の衛星が 1 機以下なら
 * その衛星も除く規則（FAQ 行 58、007 §5-6）は `OrbitResolver::nearMissIds()` が人数を見て行う。
 */
final class NearMissSuppress implements ModifierPart
{
    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        return $query->satelliteId !== null && $query->satelliteId === $host->satelliteId;
    }

    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
    {
        if (!$value instanceof NearMissAdjust) {
            throw new \InvalidArgumentException('NEAR_MISS_SUPPRESS folds a NearMissAdjust');
        }

        return $value->withExempt();
    }
}
