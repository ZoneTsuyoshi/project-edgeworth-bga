<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

/**
 * 現在のフェイズ（グローバル値 flow_phase。010 §7.2）。セットアップの配備フェイズは Deploy。
 */
enum FlowPhase: string
{
    case Setup = 'SETUP';
    case Planning = 'PLANNING';
    case Budget = 'BUDGET';
    case Deploy = 'DEPLOY';
    case LaunchTurn = 'LAUNCH_TURN';
    case Link = 'LINK';
    case Proposal = 'PROPOSAL';
    case Orbit = 'ORBIT';
    case End = 'END';
}
