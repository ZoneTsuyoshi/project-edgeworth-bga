<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts;

use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Effects\Parts\Result\EstablishLinkPart;
use Bga\Games\projectedgeworth\Effects\Query\EffectQueries;
use Bga\Games\projectedgeworth\Effects\Query\UseMods;
use Bga\Games\projectedgeworth\Material\PartId;

/**
 * E-6（通信確立。011 §8）の結果部品の登録: R11 `ESTABLISH_LINK`。
 * 対象区域の拡張・先駆コストの免除・通信確立コストの修正・禁止 F01（`LINK_THIS`）の問い合わせに `EffectQueries` を、起動の修正（`USE_MODS`）に
 * `UseMods` を、区域の種類・隣接に `BoardLayout` を渡す。新しい条件はない（K01・K04・K07・K08 は E-1〜E-3 で登録済み）。
 */
final class PartsE6
{
    private function __construct()
    {
    }

    public static function register(PartRegistry $registry, EffectQueries $queries, UseMods $useMods, BoardLayout $layout): PartRegistry
    {
        return $registry->register(PartId::EstablishLink, new EstablishLinkPart($queries, $useMods, $layout));
    }
}
