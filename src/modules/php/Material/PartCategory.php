<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 効果の部品の種別（011 §2-1）。
 */
enum PartCategory: string
{
    /** 結果部品（R）: 状態を変える */
    case Result = 'R';
    /** 修正部品（M）: 数量・対象範囲・コストを変える。状態を変えない */
    case Modifier = 'M';
    /** 禁止部品（F）: 選択肢を取り除く */
    case Forbid = 'F';
}
