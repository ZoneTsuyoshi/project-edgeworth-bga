<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

/**
 * II 配備のステージ / [Reset] の結果。通知はすべて本人宛て（バリアまで他人に見せない）。
 */
final class StageResult
{
    /**
     * @param list<Notice> $notices 本人宛ての通知
     * @param bool $wasConfirmed 確定済みだった確定を取り消した（BGA の側で本人をアクティブに戻す必要がある）
     */
    public function __construct(
        public readonly array $notices,
        public readonly bool $wasConfirmed,
    ) {
    }
}
