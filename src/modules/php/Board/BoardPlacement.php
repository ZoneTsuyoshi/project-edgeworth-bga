<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/** ボード配置の並べ方（003 §2.4）。 */
enum BoardPlacement: string
{
    case EARTH = 'earth';
    case RANDOM_NO_FLIP = 'random_no_flip';
    case RANDOM_FLIP = 'random_flip';

    /**
     * ゲームオプション 100（1〜6）を面と並べ方に分解する（004 §7）。
     *
     * @return array{0: BoardFace, 1: BoardPlacement}
     */
    public static function fromOptionValue(int $value): array
    {
        return match ($value) {
            1 => [BoardFace::STANDARD, self::EARTH],
            2 => [BoardFace::STANDARD, self::RANDOM_NO_FLIP],
            3 => [BoardFace::STANDARD, self::RANDOM_FLIP],
            4 => [BoardFace::ADVANCED, self::EARTH],
            5 => [BoardFace::ADVANCED, self::RANDOM_NO_FLIP],
            6 => [BoardFace::ADVANCED, self::RANDOM_FLIP],
            default => throw new \InvalidArgumentException("Unknown board layout option: {$value}"),
        };
    }
}
