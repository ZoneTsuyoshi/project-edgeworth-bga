<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts;

use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Material\EffectSpec;

/**
 * 修正部品 M01〜M13（011 §5-1）。状態を持たず、状態を変えない。複数あれば加算で重なる（007 §5-1）。
 */
interface ModifierPart
{
    /** この問い合わせに効くか（`scope`・`type` など部品のパラメータの判定。稼働条件と `conditions` はエンジンが先に見る） */
    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool;

    /**
     * 値を畳み込む。値の型は問い合わせごと（コストは `CostBreakdown`、対象区域は `list<int>`、ニアミスは `NearMissAdjust` など）。
     */
    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed;
}
