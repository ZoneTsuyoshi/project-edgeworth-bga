<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Modifier;

use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Parts\ModifierPart;
use Bga\Games\projectedgeworth\Effects\Query\NearMissAdjust;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Material\EffectSpec;

/**
 * M12 `NEAR_MISS_FUEL_MOD`: このカードのある衛星のニアミスの燃料消費（基本 2）を `value`（1）にする（P085）。
 * 最初から 1 を消費する扱いで、2 消費してから 1 回復するのではない（007 §5-5、FAQ 行 35）。
 */
final class NearMissFuelMod implements ModifierPart
{
    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        return $query->satelliteId !== null && $query->satelliteId === $host->satelliteId;
    }

    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
    {
        if (!$value instanceof NearMissAdjust) {
            throw new \InvalidArgumentException('NEAR_MISS_FUEL_MOD folds a NearMissAdjust');
        }

        return $value->withFuel(min($value->fuel, (int) $spec->params['value']));
    }
}
