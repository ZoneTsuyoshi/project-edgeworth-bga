<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts;

use Bga\Games\ProjectEdgeworth\Effects\Parts\Result\DeployDeckTopPart;
use Bga\Games\ProjectEdgeworth\Effects\Parts\Result\DeployFromHandPart;
use Bga\Games\ProjectEdgeworth\Effects\Parts\Result\RearrangeStandbyPart;
use Bga\Games\ProjectEdgeworth\Effects\Query\EffectQueries;
use Bga\Games\ProjectEdgeworth\Effects\Query\UseMods;
use Bga\Games\ProjectEdgeworth\Material\PartId;

/**
 * E-5（配備。011 §8）の結果部品の登録: R09 `DEPLOY_FROM_HAND`、R10 `DEPLOY_DECK_TOP`、R12 `REARRANGE_STANDBY`。
 * 配備先の禁止 F01（`DEPLOY_TO_THIS`）と配備コストの修正の確認に `EffectQueries` を、起動の修正（`USE_MODS`）に `UseMods` を渡す。
 * 新しい条件はない（K01・K02 は E-1・E-2 で登録済み）。
 */
final class PartsE5
{
    private function __construct()
    {
    }

    public static function register(PartRegistry $registry, EffectQueries $queries, UseMods $useMods): PartRegistry
    {
        return $registry
            ->register(PartId::DeployFromHand, new DeployFromHandPart($queries, $useMods))
            ->register(PartId::DeployDeckTop, new DeployDeckTopPart($queries))
            ->register(PartId::RearrangeStandby, new RearrangeStandbyPart($queries));
    }
}
