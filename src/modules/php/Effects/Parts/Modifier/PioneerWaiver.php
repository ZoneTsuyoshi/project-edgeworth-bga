<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Modifier;

use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Parts\ModifierPart;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Material\EffectSpec;

/**
 * M05 `PIONEER_WAIVER`: このカードのある衛星が通信確立するときの、他プレイヤーへの先駆コストを 0 にする（P055。FAQ 行 57）。
 * 区域コストは必要。値は「免除するか」の真偽。
 */
final class PioneerWaiver implements ModifierPart
{
    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        return $query->satelliteId !== null && $query->satelliteId === $host->satelliteId;
    }

    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
    {
        return true;
    }
}
