<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts;

use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Effects\Parts\Result\MoveSatellitePart;
use Bga\Games\projectedgeworth\Effects\Parts\Result\SwapSatellitesPart;
use Bga\Games\projectedgeworth\Effects\Parts\Result\TransferFuelPart;
use Bga\Games\projectedgeworth\Effects\Parts\Result\TurnSatellitePart;
use Bga\Games\projectedgeworth\Effects\Query\EffectQueries;
use Bga\Games\projectedgeworth\Material\PartId;

/**
 * E-4（衛星の操作。011 §8）の結果部品の登録: R06 `TRANSFER_FUEL`、R07 `MOVE_SATELLITE`、R08 `TURN_SATELLITE`、R13 `SWAP_SATELLITES`。
 * 移動は盤面の形（反射・左右の接続）が要るので `BoardLayout` を、転回は禁止 F01（`TURN_THIS`）の確認に `EffectQueries` を渡す。
 * 新しい条件はない（K01・K03・K07・K08 は E-1・E-2 で登録済み）。
 */
final class PartsE4
{
    private function __construct()
    {
    }

    public static function register(PartRegistry $registry, BoardLayout $layout, EffectQueries $queries): PartRegistry
    {
        return $registry
            ->register(PartId::TransferFuel, new TransferFuelPart())
            ->register(PartId::MoveSatellite, new MoveSatellitePart($layout))
            ->register(PartId::TurnSatellite, new TurnSatellitePart($queries))
            ->register(PartId::SwapSatellites, new SwapSatellitesPart());
    }
}
