<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Scoring;

/**
 * 通信確立済みの区域に置いたキューブ 1 つ（010 の link）。
 * 1 区域に自分のキューブは高々 1 つ。slot は区域内のマス（0 = 左上 = 1 番目。003 §5）。
 */
final class LinkedCube
{
    public function __construct(
        public readonly int $cellId,
        public readonly int $slot,
    ) {
    }
}
