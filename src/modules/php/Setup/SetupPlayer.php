<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Setup;

use Bga\Games\ProjectEdgeworth\Material\SatelliteColor;

/**
 * セットアップ対象のプレイヤー（席順の並びで渡す）。色は BGA の player_color から決める。
 */
final readonly class SetupPlayer
{
    /** BGA の player_color（gameinfos の player_colors。012 未確定 No.2）と衛星の色の対応 */
    private const BGA_COLORS = [
        '0000ff' => SatelliteColor::Blue,
        '008000' => SatelliteColor::Green,
        'ff0000' => SatelliteColor::Red,
        'ffa500' => SatelliteColor::Yellow,
    ];

    public function __construct(public int $id, public SatelliteColor $color)
    {
    }

    public static function fromBgaColor(int $id, string $hex): self
    {
        $color = self::BGA_COLORS[strtolower(ltrim($hex, '#'))]
            ?? throw new \InvalidArgumentException("Unknown player color: {$hex}");
        return new self($id, $color);
    }
}
