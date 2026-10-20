<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Modifier;

use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Parts\ModifierPart;
use Bga\Games\projectedgeworth\Effects\Query\CostBreakdown;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Material\EffectSpec;

/**
 * M03 `LINK_COST_MOD`: 通信確立コストを引く（011 §2-3、007 §5-4 の手順 5）。区域コストから先に引く配分は呼び出し側
 * （`LinkPhase::cost()`）が行う。特殊通信確立の +5 は下限 0 の後で、この部品は関与しない（FAQ 行 74）。
 *
 * パラメータ: `delta`、`scope`（`ALL_OWN` = 自分の全ての衛星 / `LINKING_SATELLITE_IS_THIS` = 通信確立する衛星がこのカードのある衛星）。
 * 地域・区域の種類の条件（K04・K05）は `EffectSpec::$conditions` が表す。
 */
final class LinkCostMod implements ModifierPart
{
    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        return match ((string) ($spec->params['scope'] ?? 'ALL_OWN')) {
            'ALL_OWN' => $host->playerId === $query->playerId,
            'LINKING_SATELLITE_IS_THIS' => $query->satelliteId !== null && $query->satelliteId === $host->satelliteId,
            default => throw new \LogicException('LINK_COST_MOD needs scope ALL_OWN / LINKING_SATELLITE_IS_THIS'),
        };
    }

    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
    {
        if (!$value instanceof CostBreakdown) {
            throw new \InvalidArgumentException('LINK_COST_MOD folds a CostBreakdown');
        }

        return $value->withModifier($spec->cardKey, (int) $spec->params['delta']);
    }
}
