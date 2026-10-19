<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

use Bga\Games\ProjectEdgeworth\Data\Satellite;

/**
 * 既定のフック: 効果による修正も禁止もない（カード効果は I-12 以降）。
 */
final class NoActionHooks implements ActionHooks
{
    public function deployCostReduction(int $playerId, Satellite $target, int $cardNumber, int $attachedCount): int
    {
        return 0;
    }

    public function launchCostReduction(int $playerId, Satellite $satellite): int
    {
        return 0;
    }

    public function extraLinkTargets(int $playerId, Satellite $satellite, StepMode $mode): array
    {
        return [];
    }

    public function pioneerWaived(int $playerId, Satellite $satellite): bool
    {
        return false;
    }

    public function linkCostReduction(int $playerId, Satellite $satellite, int $cell): int
    {
        return 0;
    }

    public function isForbidden(ForbiddenAction $action, Satellite $satellite): bool
    {
        return false;
    }

    public function proposalCounts(int $playerId, bool $bonus, ProposalCounts $base): ProposalCounts
    {
        return $base;
    }

    public function budgetMainGain(int $playerId, int $base): int
    {
        return $base;
    }

    public function sellPrice(int $playerId, int $base): int
    {
        return $base;
    }
}
