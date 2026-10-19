<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * 効果発動タイミング（002 §3.3）。ANYTIME と LAUNCHED_OR_TURNED は凡例に無く実カードにのみ存在する。
 */
enum EffectTiming: string
{
    case PlanningPhase = 'PLANNING_PHASE'; // 計画フェイズ中
    case BudgetPhase = 'BUDGET_PHASE'; // 予算発行フェイズ中
    case Deployed = 'DEPLOYED'; // 自分のカードが配備されたとき
    case Launched = 'LAUNCHED'; // 自分の衛星が打ち上げられたとき
    case Turned = 'TURNED'; // 自分の衛星が転回したとき
    case Linked = 'LINKED'; // 自分の衛星が通信確立したとき
    case ProposalPhase = 'PROPOSAL_PHASE'; // 企画フェイズ中
    case OrbitPhase = 'ORBIT_PHASE'; // 循環フェイズ中
    case Anytime = 'ANYTIME'; // ALL アイコン（いつでも）
    case LaunchedOrTurned = 'LAUNCHED_OR_TURNED'; // 打ち上げ/転回（R019 のみ）
}
