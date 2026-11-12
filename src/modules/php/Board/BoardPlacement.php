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
    case RANDOM_FLIP = 'random_flip';

    /**
     * ゲームオプション 100（1・3・4・6）を面と並べ方に分解する（004 §7）。
     *
     * @return array{0: BoardFace, 1: BoardPlacement}
     */
    public static function fromOptionValue(int $value): array
    {
        // 2・5（旧 Random w/o Flip）は廃止した欠番（2026-11-12 作者指示）。値のキーは変えないので予約のまま
        return match ($value) {
            1 => [BoardFace::STANDARD, self::EARTH],
            3 => [BoardFace::STANDARD, self::RANDOM_FLIP],
            4 => [BoardFace::ADVANCED, self::EARTH],
            6 => [BoardFace::ADVANCED, self::RANDOM_FLIP],
            default => throw new \InvalidArgumentException("Unknown board layout option: {$value}"),
        };
    }
}
