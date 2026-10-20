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
 * M10 `SELL_PRICE_MOD`: 手札の売却額（基本 3）に `delta` を足す（P077・R009 の +2。加算で重なり、FAQ 行 60 で 7 マヤ）。
 * 企画・効果の捨てには、呼び出し側がこの問い合わせをしないことで効かせない（FAQ 行 31・60・70）。稼働中の衛星のカードだけが
 * 数えられるのは `ACTIVE_ONLY`（007 §5-5）。
 */
final class SellPriceMod implements ModifierPart
{
    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        return $host->playerId === $query->playerId;
    }

    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
    {
        return (int) $value + (int) $spec->params['delta'];
    }
}
