<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Modifier;

use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Parts\ModifierPart;
use Bga\Games\projectedgeworth\Effects\Query\ProposalOptionPlan;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Material\EffectSpec;

/**
 * M07 `PROPOSAL_DISCARD_SCOPE`（P068）: 企画で捨てるカードに、企画中に引いたカード以外（手札）も選べるようにする。捨てる枚数は変わらない。
 * `PROPOSAL_OPTIONS` の問い合わせ（`ProposalOptions::plan()`）が `ProposalOptionPlan` に畳み込む。
 */
final class ProposalDiscardScopeMod implements ModifierPart
{
    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        return $host->playerId === $query->playerId;
    }

    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
    {
        if (!$value instanceof ProposalOptionPlan) {
            throw new \InvalidArgumentException('PROPOSAL_DISCARD_SCOPE folds a ProposalOptionPlan');
        }

        return $value->withHandDiscard();
    }
}
