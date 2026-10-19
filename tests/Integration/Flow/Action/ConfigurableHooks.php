<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Integration\Flow\Action;

use Bga\Games\ProjectEdgeworth\Data\Satellite;
use Bga\Games\ProjectEdgeworth\Flow\Action\ActionHooks;
use Bga\Games\ProjectEdgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\ProjectEdgeworth\Flow\Action\ProposalCounts;
use Bga\Games\ProjectEdgeworth\Flow\Action\StepMode;

/**
 * テスト用のフック。値を直接書き換えて、効果による修正・禁止を再現する。
 */
final class ConfigurableHooks implements ActionHooks
{
    public int $deployReduction = 0;
    public int $launchReduction = 0;
    /** @var list<int> */
    public array $extraTargets = [];
    public bool $pioneerWaived = false;
    public int $linkReduction = 0;
    /** @var array<string, list<int>> 禁止する行動 => 衛星 ID */
    public array $forbidden = [];
    public ?ProposalCounts $mainCounts = null;
    public ?ProposalCounts $bonusCounts = null;
    public int $budgetExtra = 0;
    public int $sellExtra = 0;

    public function deployCostReduction(int $playerId, Satellite $target, int $cardNumber, int $attachedCount): int
    {
        return $this->deployReduction;
    }

    public function launchCostReduction(int $playerId, Satellite $satellite): int
    {
        return $this->launchReduction;
    }

    public function extraLinkTargets(int $playerId, Satellite $satellite, StepMode $mode): array
    {
        return $this->extraTargets;
    }

    public function pioneerWaived(int $playerId, Satellite $satellite): bool
    {
        return $this->pioneerWaived;
    }

    public function linkCostReduction(int $playerId, Satellite $satellite, int $cell): int
    {
        return $this->linkReduction;
    }

    public function isForbidden(ForbiddenAction $action, Satellite $satellite): bool
    {
        return in_array($satellite->id, $this->forbidden[$action->value] ?? [], true);
    }

    public function proposalCounts(int $playerId, bool $bonus, ProposalCounts $base): ProposalCounts
    {
        return ($bonus ? $this->bonusCounts : $this->mainCounts) ?? $base;
    }

    public function budgetMainGain(int $playerId, int $base): int
    {
        return $base + $this->budgetExtra;
    }

    public function sellPrice(int $playerId, int $base): int
    {
        return $base + $this->sellExtra;
    }
}
