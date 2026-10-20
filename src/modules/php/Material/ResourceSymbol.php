<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 効果が触れる資源の記号（007 §6-2）。値は ASCII（`−` は `-`）。
 */
enum ResourceSymbol: string
{
    case MayaGain = 'M+'; // マヤの獲得（加算）
    case MayaPay = 'M-'; // マヤの支払い・残高を見て選ぶ（読み書き）
    case BudgetGain = 'B+'; // 予算の上昇（加算）
    case HandDraw = 'H+'; // プロジェクトカードを引く（加算）
    case HandChoose = 'H*'; // 手札を見て選ぶ・捨てる・配備する（読み書き）
    case FuelRecover = 'F+'; // 燃料の回復（加算）
    case FuelConsume = 'F-'; // 燃料の消費（加算）
    case Position = 'P'; // 衛星の位置（読み書き）
    case Direction = 'D'; // 衛星の向き（読み書き）
    case Composition = 'C'; // 衛星のカード構成を変える（読み書き）
    case CompositionRead = 'Cr'; // 衛星のカード構成を読む（読み）
    case Link = 'L'; // 通信確立（読み書き）
    case Activity = 'A'; // 稼働状態を変える（読み書き）

    /** 同じ資源か（`M+` と `M-` は同じ資源 M、`C` と `Cr` は同じ資源 C など） */
    public function group(): string
    {
        return match ($this) {
            self::MayaGain, self::MayaPay => 'M',
            self::BudgetGain => 'B',
            self::HandDraw, self::HandChoose => 'H',
            self::FuelRecover, self::FuelConsume => 'F',
            self::Composition, self::CompositionRead => 'C',
            default => $this->value,
        };
    }

    /** 読み書き（`M-` `H*` `P` `D` `C` `L` `A`）。他のどの効果とも、同じ資源で触れ合えば衝突する */
    public function isReadWrite(): bool
    {
        return match ($this) {
            self::MayaPay, self::HandChoose, self::Position, self::Direction, self::Composition, self::Link, self::Activity => true,
            default => false,
        };
    }
}
