<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts;

use Bga\Games\projectedgeworth\Effects\Context\EffectContext;
use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Pool\PoolItem;
use Bga\Games\projectedgeworth\Material\EffectSpec;

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
