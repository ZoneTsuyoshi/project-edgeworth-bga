<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

/**
 * 送る通知 1 件（010 §10）。to が null なら全員（観戦者を含む）、そうでなければそのプレイヤーだけ。
 * BGA に依存しない。実際の送信は Game::peSend が行う。
 */
final class Notice
{
    /**
     * @param array<string, mixed> $args
     */
    public function __construct(
        public readonly string $name,
        public readonly string $message,
        public readonly array $args = [],
        public readonly ?int $to = null,
    ) {
    }
}
