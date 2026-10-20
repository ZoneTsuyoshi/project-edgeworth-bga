<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Forbid;

use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Parts\ForbidPart;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Material\EffectSpec;

/**
 * F01 `FORBID`: このカードのある衛星に対する行動を認めない（011 §2-4）。`params['action']` は `ForbiddenAction` の値
 * （`DEPLOY_TO_THIS` = P033、`TURN_THIS` = P046、`LINK_THIS` = P066）。選択肢を作る時点と効果の解決時の両方で、
 * `ALLOWED` の問い合わせとして評価される（007 §3-2）。
 */
final class Forbid implements ForbidPart
{
    public function forbids(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        return $query->action !== null
            && $query->satelliteId !== null
            && $query->satelliteId === $host->satelliteId
            && $query->action->value === (string) ($spec->params['action'] ?? '');
    }
}
