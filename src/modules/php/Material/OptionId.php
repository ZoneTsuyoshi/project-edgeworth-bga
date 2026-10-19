<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * ゲームオプションの ID（004 §7。gameoptions.jsonc と一致させる）。
 */
final class OptionId
{
    public const BOARD_LAYOUT = 100;
    public const BOARD_SIZE = 101;
    public const AWARD_CARDS = 102;
    public const SATELLITE_PLAN = 103;
    public const STARTING_HAND = 104;

    private function __construct()
    {
    }
}
