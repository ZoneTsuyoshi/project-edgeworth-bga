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
 * M01 `DEPLOY_COST_MOD`: 配備コストを引く（011 §2-3、007 §5-2）。下限 0 は `CostBreakdown` が最後に 1 回適用する。
 *
 * パラメータ: `delta`（引くときは負）、`scope`（`THIS_SATELLITE` = このカードのある衛星への配備 / `ALL_OWN` = 自分の全ての衛星 /
 * `SELF_CARD` = 配備しようとするこのカード自身。P026）、`type`（省略 = 全て。配備するカードのタイプ）、
 * `perAttachedCard`（真なら delta × 対象の衛星に配備前から付いているカードの枚数。P026）。
 */
final class DeployCostMod implements ModifierPart
{
    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        $inScope = match ((string) ($spec->params['scope'] ?? 'ALL_OWN')) {
            'THIS_SATELLITE' => $query->satelliteId !== null && $query->satelliteId === $host->satelliteId,
            'ALL_OWN' => $host->playerId === $query->playerId,
            'SELF_CARD' => true,
            default => throw new \LogicException('DEPLOY_COST_MOD needs scope THIS_SATELLITE / ALL_OWN / SELF_CARD'),
        };
        if (!$inScope) {
            return false;
        }
        $type = $spec->params['type'] ?? null;

        return $type === null || $query->cardType?->value === (string) $type;
    }

    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
    {
        if (!$value instanceof CostBreakdown) {
            throw new \InvalidArgumentException('DEPLOY_COST_MOD folds a CostBreakdown');
        }
        $times = ($spec->params['perAttachedCard'] ?? false) === true ? $query->attachedCount : 1;

        return $value->withModifier($spec->cardKey, (int) $spec->params['delta'] * $times);
    }
}
