<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects;

/**
 * 窓を閉じたときの結果。logs から通知（保留通知のバリアでの一斉送信。010 §10.5）を組み立てる。
 */
final readonly class WindowResult
{
    /**
     * @param list<LogEntry> $logs 窓の最中の結果のログ（時系列）
     */
    public function __construct(
        public array $logs,
        public int $resolvedCount,
        public bool $aborted,
    ) {
    }
}
