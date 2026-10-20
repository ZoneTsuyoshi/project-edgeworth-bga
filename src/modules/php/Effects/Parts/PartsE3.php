<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts;

use Bga\Games\projectedgeworth\Effects\Parts\Modifier\ProposalDiscardScopeMod;
use Bga\Games\projectedgeworth\Effects\Parts\Modifier\ProposalKeepPurchaseMod;
use Bga\Games\projectedgeworth\Effects\Parts\Result\DiscardHandPart;
use Bga\Games\projectedgeworth\Effects\Parts\Result\GrantActionBonusPart;
use Bga\Games\projectedgeworth\Material\PartId;

/**
 * E-3（任意の効果と問い。011 §8）の部品の登録: R04 `DISCARD_HAND`、R14 `GRANT_ACTION_BONUS`、M07 `PROPOSAL_DISCARD_SCOPE`、
 * M08 `PROPOSAL_KEEP_PURCHASE`。P005・P006・P069・P086 は E-2 の R01〜R03 を、起動の修正（P019〜P021・P036・R021〜R025）は
 * E-1 の M01〜M03 を使う。条件 K04・K05・K13 の登録は `ConditionsE3::register()`。
 */
final class PartsE3
{
    private function __construct()
    {
    }

    public static function register(PartRegistry $registry): PartRegistry
    {
        return $registry
            ->register(PartId::DiscardHand, new DiscardHandPart())
            ->register(PartId::GrantActionBonus, new GrantActionBonusPart())
            ->register(PartId::ProposalDiscardScope, new ProposalDiscardScopeMod())
            ->register(PartId::ProposalKeepPurchase, new ProposalKeepPurchaseMod());
    }
}
