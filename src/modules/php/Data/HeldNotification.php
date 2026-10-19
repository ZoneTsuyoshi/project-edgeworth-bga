<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Data;

/**
 * held_notif テーブルの 1 行 = 窓の中で保留した公開通知（010 §10.5）。
 * メッセージの文字列は保存せず、type と variant から Flow/Notification が clienttranslate() のリテラルを引く。
 */
final class HeldNotification
{
    /**
     * @param array<mixed> $args
     */
    public function __construct(
        public readonly int $id,
        public readonly int $originPlayerId,
        public readonly string $type,
        public readonly string $variant,
        public readonly array $args,
    ) {
    }
}
