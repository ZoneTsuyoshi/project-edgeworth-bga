<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 効果が働く時点（011 §3、28 種）。窓 14・ステップ内 3・問い合わせ 11。
 * `windowSymbol()` は 007 §2・§4 の窓の記号（窓のないものは null）。
 */
enum Hook: string
{
    // 窓フック（011 §3-1）
    case PlanningRetrieve = 'PLANNING_RETRIEVE';
    case PlanningReveal = 'PLANNING_REVEAL';
    case BudgetStart = 'BUDGET_START';
    case BonusDeclare = 'BONUS_DECLARE';
    case BudgetBonusGain = 'BUDGET_BONUS_GAIN';
    case BudgetFree = 'BUDGET_FREE';
    case Deployed = 'DEPLOYED';
    case Launched = 'LAUNCHED';
    case Turned = 'TURNED';
    case Linked = 'LINKED';
    case ProposalAfter = 'PROPOSAL_AFTER';
    case OrbitStart = 'ORBIT_START';
    case OrbitEntered = 'ORBIT_ENTERED';
    case Landed = 'LANDED';

    // ステップ内フック（011 §3-1）
    case ProposalDrawn = 'PROPOSAL_DRAWN';
    case ProposalOptions = 'PROPOSAL_OPTIONS';
    case ActionBonusStep = 'ACTION_BONUS_STEP';

    // 問い合わせフック（011 §3-2）
    case DeployCost = 'DEPLOY_COST';
    case LaunchCost = 'LAUNCH_COST';
    case LinkTargets = 'LINK_TARGETS';
    case LinkPioneer = 'LINK_PIONEER';
    case LinkCost = 'LINK_COST';
    case Allowed = 'ALLOWED';
    case ProposalCounts = 'PROPOSAL_COUNTS';
    case BudgetMainGain = 'BUDGET_MAIN_GAIN';
    case SellPrice = 'SELL_PRICE';
    case FuelConsume = 'FUEL_CONSUME';
    case NearMiss = 'NEAR_MISS';

    public function kind(): HookKind
    {
        return match ($this) {
            self::PlanningRetrieve, self::PlanningReveal, self::BudgetStart, self::BonusDeclare, self::BudgetBonusGain,
            self::BudgetFree, self::Deployed, self::Launched, self::Turned, self::Linked, self::ProposalAfter,
            self::OrbitStart, self::OrbitEntered, self::Landed => HookKind::Window,
            self::ProposalDrawn, self::ProposalOptions, self::ActionBonusStep => HookKind::Step,
            default => HookKind::Query,
        };
    }

    /** 007 の窓・数量の決定時点の記号（011 §3-1・§3-2 の「007 の窓」列）。`ALLOWED` と `ACTION_BONUS_STEP` は記号なし */
    public function windowSymbol(): ?string
    {
        return match ($this) {
            self::PlanningRetrieve => 'PL1',
            self::PlanningReveal => 'PL2',
            self::BudgetStart => 'B0',
            self::BonusDeclare => 'XD',
            self::BudgetBonusGain => 'B2',
            self::BudgetFree => 'B3',
            self::Deployed => 'DE',
            self::Launched => 'LE',
            self::Turned => 'TE',
            self::Linked => 'KE',
            self::ProposalAfter => 'PB',
            self::OrbitStart => 'O0',
            self::OrbitEntered => 'O1',
            self::Landed => 'O4',
            self::ProposalDrawn, self::ProposalOptions, self::ProposalCounts => 'PA',
            self::DeployCost => 'DC',
            self::LaunchCost => 'LC',
            self::LinkTargets, self::LinkPioneer, self::LinkCost => 'KC',
            self::BudgetMainGain => 'B1',
            self::SellPrice => 'AN',
            self::FuelConsume => 'O2',
            self::NearMiss => 'O3',
            self::Allowed, self::ActionBonusStep => null,
        };
    }
}
