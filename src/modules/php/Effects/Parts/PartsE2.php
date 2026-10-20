<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts;

use Bga\Games\ProjectEdgeworth\Effects\Parts\Result\ChangeBudgetPart;
use Bga\Games\ProjectEdgeworth\Effects\Parts\Result\DrawCardsPart;
use Bga\Games\ProjectEdgeworth\Effects\Parts\Result\GainMayaPart;
use Bga\Games\ProjectEdgeworth\Effects\Parts\Result\KeepPlottedActionsPart;
use Bga\Games\ProjectEdgeworth\Effects\Parts\Result\RecoverFuelPart;
use Bga\Games\ProjectEdgeworth\Material\PartId;

/**
 * E-2（強制の反応効果）の結果部品の登録: R01 `GAIN_MAYA`、R02 `CHANGE_BUDGET`、R03 `DRAW_CARDS`、R05 `RECOVER_FUEL`、R15 `KEEP_PLOTTED_ACTIONS`。
 */
final class PartsE2
{
    private function __construct()
    {
    }

    public static function register(PartRegistry $registry): PartRegistry
    {
        return $registry
            ->register(PartId::GainMaya, new GainMayaPart())
            ->register(PartId::ChangeBudget, new ChangeBudgetPart())
            ->register(PartId::DrawCards, new DrawCardsPart())
            ->register(PartId::RecoverFuel, new RecoverFuelPart())
            ->register(PartId::KeepPlottedActions, new KeepPlottedActionsPart());
    }
}
