<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

/**
 * 同時選択で 1 人が確定した結果。
 */
final class ConfirmResult
{
    /**
     * @param bool $first このプレイヤーの、この状態での最初の確定か（選び直しではないか）
     * @param bool $allConfirmed DB の確定一覧がそろったか
     * @param list<Notice> $notices
     */
    public function __construct(
        public readonly bool $first,
        public readonly bool $allConfirmed,
        public readonly array $notices,
    ) {
    }
}
