<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts;

use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Query\QueryContext;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;

/**
 * 禁止部品 F01（011 §5-1）。
 */
interface ForbidPart
{
    /** `$query->action` を、`$query->satelliteId` の衛星に認めないか */
    public function forbids(QueryContext $query, EffectSpec $spec, EffectHost $host): bool;
}
