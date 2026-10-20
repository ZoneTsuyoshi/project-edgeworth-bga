<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts;

use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Effects\Context\GameView;
use Bga\Games\projectedgeworth\Effects\Parts\Forbid\Forbid;
use Bga\Games\projectedgeworth\Effects\Parts\Modifier\BudgetGainMod;
use Bga\Games\projectedgeworth\Effects\Parts\Modifier\DeployCostMod;
use Bga\Games\projectedgeworth\Effects\Parts\Modifier\FuelConsumeMod;
use Bga\Games\projectedgeworth\Effects\Parts\Modifier\LaunchCostMod;
use Bga\Games\projectedgeworth\Effects\Parts\Modifier\LinkCostMod;
use Bga\Games\projectedgeworth\Effects\Parts\Modifier\LinkTargetExpand;
use Bga\Games\projectedgeworth\Effects\Parts\Modifier\NearMissFuelMod;
use Bga\Games\projectedgeworth\Effects\Parts\Modifier\NearMissSuppress;
use Bga\Games\projectedgeworth\Effects\Parts\Modifier\PioneerWaiver;
use Bga\Games\projectedgeworth\Effects\Parts\Modifier\ProposalCountMod;
use Bga\Games\projectedgeworth\Effects\Parts\Modifier\SellPriceMod;
use Bga\Games\projectedgeworth\Material\PartId;

/**
 * E-1（常時の修正・禁止。011 §8）の部品の登録。M01〜M06・M09〜M13 と F01（M07・M08 は E-3）。
 * 対象区域の拡張（M04）は盤面の地形が要るので `BoardLayout` を、予算発行の加算（M09）は衛星に配備されたカードが要るので
 * `GameView` を渡す。条件 K03・K06・K12 の登録は `ConditionsE1::register()`。
 */
final class PartsE1
{
    private function __construct()
    {
    }

    public static function register(PartRegistry $registry, GameView $view, BoardLayout $layout): PartRegistry
    {
        return $registry
            ->register(PartId::DeployCostMod, new DeployCostMod())
            ->register(PartId::LaunchCostMod, new LaunchCostMod())
            ->register(PartId::LinkCostMod, new LinkCostMod())
            ->register(PartId::LinkTargetExpand, new LinkTargetExpand($layout))
            ->register(PartId::PioneerWaiver, new PioneerWaiver())
            ->register(PartId::ProposalCountMod, new ProposalCountMod())
            ->register(PartId::BudgetGainMod, new BudgetGainMod($view))
            ->register(PartId::SellPriceMod, new SellPriceMod())
            ->register(PartId::FuelConsumeMod, new FuelConsumeMod())
            ->register(PartId::NearMissFuelMod, new NearMissFuelMod())
            ->register(PartId::NearMissSuppress, new NearMissSuppress())
            ->register(PartId::Forbid, new Forbid());
    }
}
