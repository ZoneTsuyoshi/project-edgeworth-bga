<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/**
 * 六角形の 6 方向（003 §1.2）。ダブルド座標の差分 (dx, dy) を持つ。
 * 衛星の向きにも 6 方向すべてを使う（003 §4.1。作者回答 2026-10-20）。
 */
enum Direction: string
{
    case E = 'E';
    case W = 'W';
    case NE = 'NE';
    case NW = 'NW';
    case SE = 'SE';
    case SW = 'SW';

    public function dx(): int
    {
        return match ($this) {
            self::E => 2,
            self::W => -2,
            self::NE, self::SE => 1,
            self::NW, self::SW => -1,
        };
    }

    public function dy(): int
    {
        return match ($this) {
            self::E, self::W => 0,
            self::NE, self::NW => -1,
            self::SE, self::SW => 1,
        };
    }

    public function opposite(): self
    {
        return match ($this) {
            self::E => self::W,
            self::W => self::E,
            self::NE => self::SW,
            self::SW => self::NE,
            self::NW => self::SE,
            self::SE => self::NW,
        };
    }

    /** 上下反転（反射）。E・W は変わらない。 */
    public function flipVertical(): self
    {
        return match ($this) {
            self::NE => self::SE,
            self::SE => self::NE,
            self::NW => self::SW,
            self::SW => self::NW,
            default => $this,
        };
    }

    /** 時計回りに 60° 回転: E→SE→SW→W→NW→NE→E */
    public function rotateClockwise(): self
    {
        return match ($this) {
            self::E => self::SE,
            self::SE => self::SW,
            self::SW => self::W,
            self::W => self::NW,
            self::NW => self::NE,
            self::NE => self::E,
        };
    }
}
