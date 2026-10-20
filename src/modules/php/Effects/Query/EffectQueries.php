<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Query;

use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Flow\Action\ProposalCounts;
use Bga\Games\projectedgeworth\Material\Hook;

/**
 * 修正の問い合わせ 11 種（011 §3-2）の型付きの入口。`ModifierQuery::fold()` に基本値と戻り値の型を与えるだけの薄い層。
 * 値の型: コスト 3 種は `CostBreakdown`、対象区域は `list<int>`、先駆コストの免除は `bool`、枚数は `ProposalCounts`、
 * ニアミスは `NearMissAdjust`、残りは `int`。
 */
final class EffectQueries
{
    public function __construct(private readonly ModifierQuery $query)
    {
    }

    /**
     * @param list<string> $useMods
     */
    public function deployCost(QueryContext $q, int $base = 0, array $useMods = [], ?EffectHost $selfHost = null): CostBreakdown
    {
        $result = $this->query->fold(Hook::DeployCost, $q, new CostBreakdown($base), $useMods, $selfHost);
        assert($result instanceof CostBreakdown);

        return $result;
    }

    /**
     * @param list<string> $useMods
     */
    public function launchCost(QueryContext $q, int $base = 0, array $useMods = []): CostBreakdown
    {
        $result = $this->query->fold(Hook::LaunchCost, $q, new CostBreakdown($base), $useMods);
        assert($result instanceof CostBreakdown);

        return $result;
    }

    /**
     * @param list<string> $useMods
     */
    public function linkCost(QueryContext $q, int $base = 0, array $useMods = []): CostBreakdown
    {
        $result = $this->query->fold(Hook::LinkCost, $q, new CostBreakdown($base), $useMods);
        assert($result instanceof CostBreakdown);

        return $result;
    }

    /**
     * @param list<int> $base
     * @param list<string> $useMods
     * @return list<int>
     */
    public function linkTargets(QueryContext $q, array $base = [], array $useMods = []): array
    {
        $result = $this->query->fold(Hook::LinkTargets, $q, $base, $useMods);
        assert(is_array($result));

        return array_values(array_unique($result));
    }

    public function pioneerWaived(QueryContext $q): bool
    {
        return $this->query->fold(Hook::LinkPioneer, $q, false) === true;
    }

    /** `ALLOWED`: 禁止効果が効いていなければ真 */
    public function allowed(QueryContext $q): bool
    {
        return !$this->query->forbidden($q);
    }

    public function proposalCounts(QueryContext $q, ProposalCounts $base): ProposalCounts
    {
        $result = $this->query->fold(Hook::ProposalCounts, $q, $base);
        assert($result instanceof ProposalCounts);

        return $result;
    }

    public function budgetMainGain(QueryContext $q, int $base): int
    {
        return (int) $this->query->fold(Hook::BudgetMainGain, $q, $base);
    }

    public function sellPrice(QueryContext $q, int $base): int
    {
        return (int) $this->query->fold(Hook::SellPrice, $q, $base);
    }

    public function fuelConsume(QueryContext $q, int $base): int
    {
        return (int) $this->query->fold(Hook::FuelConsume, $q, $base);
    }

    public function nearMiss(QueryContext $q, NearMissAdjust $base = new NearMissAdjust()): NearMissAdjust
    {
        $result = $this->query->fold(Hook::NearMiss, $q, $base);
        assert($result instanceof NearMissAdjust);

        return $result;
    }
}
