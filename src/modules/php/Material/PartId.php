<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 効果の部品（011 §2-2〜§2-4、29 種: 結果 15・修正 13・禁止 1）。値は 011 の部品名。
 */
enum PartId: string
{
    // 結果部品 R01〜R15
    case GainMaya = 'GAIN_MAYA';
    case ChangeBudget = 'CHANGE_BUDGET';
    case DrawCards = 'DRAW_CARDS';
    case DiscardHand = 'DISCARD_HAND';
    case RecoverFuel = 'RECOVER_FUEL';
    case TransferFuel = 'TRANSFER_FUEL';
    case MoveSatellite = 'MOVE_SATELLITE';
    case TurnSatellite = 'TURN_SATELLITE';
    case DeployFromHand = 'DEPLOY_FROM_HAND';
    case DeployDeckTop = 'DEPLOY_DECK_TOP';
    case EstablishLink = 'ESTABLISH_LINK';
    case RearrangeStandby = 'REARRANGE_STANDBY';
    case SwapSatellites = 'SWAP_SATELLITES';
    case GrantActionBonus = 'GRANT_ACTION_BONUS';
    case KeepPlottedActions = 'KEEP_PLOTTED_ACTIONS';

    // 修正部品 M01〜M13
    case DeployCostMod = 'DEPLOY_COST_MOD';
    case LaunchCostMod = 'LAUNCH_COST_MOD';
    case LinkCostMod = 'LINK_COST_MOD';
    case LinkTargetExpand = 'LINK_TARGET_EXPAND';
    case PioneerWaiver = 'PIONEER_WAIVER';
    case ProposalCountMod = 'PROPOSAL_COUNT_MOD';
    case ProposalDiscardScope = 'PROPOSAL_DISCARD_SCOPE';
    case ProposalKeepPurchase = 'PROPOSAL_KEEP_PURCHASE';
    case BudgetGainMod = 'BUDGET_GAIN_MOD';
    case SellPriceMod = 'SELL_PRICE_MOD';
    case FuelConsumeMod = 'FUEL_CONSUME_MOD';
    case NearMissFuelMod = 'NEAR_MISS_FUEL_MOD';
    case NearMissSuppress = 'NEAR_MISS_SUPPRESS';

    // 禁止部品 F01
    case Forbid = 'FORBID';

    public function category(): PartCategory
    {
        return match ($this) {
            self::GainMaya, self::ChangeBudget, self::DrawCards, self::DiscardHand, self::RecoverFuel, self::TransferFuel,
            self::MoveSatellite, self::TurnSatellite, self::DeployFromHand, self::DeployDeckTop, self::EstablishLink,
            self::RearrangeStandby, self::SwapSatellites, self::GrantActionBonus, self::KeepPlottedActions => PartCategory::Result,
            self::Forbid => PartCategory::Forbid,
            default => PartCategory::Modifier,
        };
    }
}
