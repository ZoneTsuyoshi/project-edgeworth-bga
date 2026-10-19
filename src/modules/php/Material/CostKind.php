<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * 効果を使う代価（011 §2-5、支払い C の 3 種）。
 */
enum CostKind: string
{
    /** C1: 固定のマヤ */
    case PayMaya = 'PAY_MAYA';
    /** C2: 段階を選んで払うマヤ（段階ごとに結果の量が変わる） */
    case PayMayaTier = 'PAY_MAYA_TIER';
    /** C3: 手札を捨てる（売却額は得ない） */
    case DiscardCost = 'DISCARD_COST';
}
