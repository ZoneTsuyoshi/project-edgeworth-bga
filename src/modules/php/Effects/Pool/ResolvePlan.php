<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Pool;

/**
 * 1 人分のプールの「次にすること」（011 §5-3 の `advance(p)` の分岐）。
 */
final readonly class ResolvePlan
{
    /**
     * @param list<PoolItem> $items Auto: 解決する 1 件 / Ask: 今答えられる効果（衝突の順序が決まっていれば 1 件）
     * @param list<list<int>> $sets Order: 順序が未決定の衝突する集合（要素 ID）
     */
    public function __construct(
        public PlanKind $kind,
        public array $items = [],
        public array $sets = [],
    ) {
    }
}
