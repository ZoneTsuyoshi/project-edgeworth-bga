<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Modifier;

use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Parts\ModifierPart;
use Bga\Games\projectedgeworth\Effects\Query\ProposalOption;
use Bga\Games\projectedgeworth\Effects\Query\ProposalOptionPlan;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Material\EffectSpec;

/**
 * M08 `PROPOSAL_KEEP_PURCHASE`（P072）: 引いたカードを見てから、マヤを払って残す枚数を増やす。`tiers` = 支払い額 => 増分（4 / 7 / 10 → +1 / +2 / +3）。
 * 残す枚数は引いた枚数を超えない（`ProposalOptions::keepCount()` が抑える）。使用済みの宿主はプランに載せない。
 */
final class ProposalKeepPurchaseMod implements ModifierPart
{
    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        return $host->playerId === $query->playerId && !$host->used;
    }

    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
    {
        if (!$value instanceof ProposalOptionPlan) {
            throw new \InvalidArgumentException('PROPOSAL_KEEP_PURCHASE folds a ProposalOptionPlan');
        }
        /** @var array<int, int> $tiers */
        $tiers = array_map('intval', (array) ($spec->params['tiers'] ?? []));

        return $value->withOption(new ProposalOption($host->key(), $spec->cardKey, ProposalOption::KEEP_PURCHASE, 0, $tiers));
    }
}
