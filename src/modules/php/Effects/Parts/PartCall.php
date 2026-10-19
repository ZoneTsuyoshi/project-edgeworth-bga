<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts;

use Bga\Games\ProjectEdgeworth\Effects\Context\EffectContext;
use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Pool\PoolItem;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;

/**
 * 結果部品の呼び出し 1 回分の入力。`host` はその時点の宿主の状態（解決の時点で引き直したもの。011 エッジケース）。
 */
final readonly class PartCall
{
    public function __construct(
        public EffectContext $ctx,
        public PoolItem $item,
        public EffectSpec $spec,
        public EffectHost $host,
        public int $stage,
    ) {
    }
}
