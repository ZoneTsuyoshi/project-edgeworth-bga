<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Context;

/**
 * ステージに積まれた通信確立（007 §7-3）。コミットは状態 32 のバリアで全員分を一斉に行う。
 * コスト（区域 + 先駆 − 修正、特殊は +5）はステージ時に予約する。
 */
final readonly class StagedLinkRequest
{
    /**
     * @param array<string, mixed> $cost 予約したコストの内訳（中身は E-6 が定める）
     */
    public function __construct(
        public int $playerId,
        public int $satelliteId,
        public int $cell,
        public string $sourceKey,
        public array $cost = [],
    ) {
    }
}
