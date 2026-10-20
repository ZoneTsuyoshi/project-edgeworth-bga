<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Question;

/**
 * 問いのフィールドの型（011 §6-2、8 種）。
 */
enum FieldType: string
{
    /** 衛星を 1 機選ぶ。答え: 衛星 ID */
    case Satellite = 'SATELLITE';
    /** 通信確立などの区域を選ぶ。答え: 区域 */
    case Area = 'AREA';
    /** 転回後の向き。答え: 方向 */
    case Direction = 'DIRECTION';
    /** 段階・量を選ぶ。答え: 整数 */
    case Amount = 'AMOUNT';
    /** 手札のカードを選ぶ。答え: カード ID の配列 */
    case Cards = 'CARDS';
    /** 依存する 2 つの選択の組（サーバーが有効な組を列挙する）。答え: 組の配列 */
    case Pairs = 'PAIRS';
    /** カードと衛星の対応（P022）。答え: カード → 衛星。中身の検証は部品が行う */
    case Assignment = 'ASSIGNMENT';
    /** 起動の修正を使うか。答え: カード ID（鍵）の配列 */
    case UseMods = 'USE_MODS';
}
