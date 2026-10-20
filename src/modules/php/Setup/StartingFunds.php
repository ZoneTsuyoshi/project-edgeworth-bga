<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Setup;

use Bga\Games\projectedgeworth\Board\BoardFace;

/**
 * 初期資金（001 §2-2、§5 手順 15）。席順（1 番手が基準プレイヤー。Q-07）で決まり、
 * 2〜3 人のときは 1〜n 番手の値を使う（U-2）。
 */
final class StartingFunds
{
    /** 表面（Standard） */
    private const STANDARD = [30, 31, 32, 33];
    /** 裏面（Advanced） */
    private const ADVANCED = [28, 30, 32, 34];

    private function __construct()
    {
    }

    /**
     * @param int $seat 席順（0 始まり。0 が基準プレイヤー）
     */
    public static function forSeat(BoardFace $face, int $seat): int
    {
        $table = $face === BoardFace::ADVANCED ? self::ADVANCED : self::STANDARD;
        return $table[$seat] ?? throw new \InvalidArgumentException("seat must be 0..3: {$seat}");
    }
}
