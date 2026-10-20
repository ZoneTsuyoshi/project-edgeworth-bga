<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * アクションの種類（002 §3.6・§9）。
 */
enum ActionKind: string
{
    case Budget = 'BUDGET'; // I
    case Deploy = 'DEPLOY'; // II
    case LaunchTurn = 'LAUNCH_TURN'; // III
    case Link = 'LINK'; // IV
    case Proposal = 'PROPOSAL'; // V
}
