<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

/**
 * 状態の処理の結果: 遷移名と、送る通知。通知は状態クラスが Game::peSend で送る。
 */
final class DriverResult
{
    /**
     * @param list<Notice> $notices
     */
    public function __construct(
        public readonly string $transition,
        public readonly array $notices = [],
    ) {
    }
}
