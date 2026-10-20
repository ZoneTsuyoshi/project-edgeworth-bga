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
 * M02 `LAUNCH_COST_MOD`: 打ち上げコストを引く（011 §2-3、007 §5-3）。打ち上げる衛星は非稼働のまま評価するので、`ACTIVE_ONLY` の
 * 修正（R006）はその衛星自身の打ち上げには効かない（FAQ 行 67）。稼働条件の判定は `ModifierQuery` が行う。
 *
 * パラメータ: `delta`、`scope`（`ALL_OWN` = 自分の全ての衛星 / `LAUNCHING_SATELLITE` = 打ち上げる衛星がこのカードのある衛星）、
 * `perAttachedCard`（真なら delta × `QueryContext::$attachedCount`。P036）。
 */
final class LaunchCostMod implements ModifierPart
{
    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        return match ((string) ($spec->params['scope'] ?? 'ALL_OWN')) {
            'ALL_OWN' => $host->playerId === $query->playerId,
            'LAUNCHING_SATELLITE' => $query->satelliteId !== null && $query->satelliteId === $host->satelliteId,
            default => throw new \LogicException('LAUNCH_COST_MOD needs scope ALL_OWN / LAUNCHING_SATELLITE'),
        };
    }

    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
    {
        if (!$value instanceof CostBreakdown) {
            throw new \InvalidArgumentException('LAUNCH_COST_MOD folds a CostBreakdown');
        }
        $times = ($spec->params['perAttachedCard'] ?? false) === true ? $query->attachedCount : 1;

        return $value->withModifier($spec->cardKey, (int) $spec->params['delta'] * $times);
    }
}
