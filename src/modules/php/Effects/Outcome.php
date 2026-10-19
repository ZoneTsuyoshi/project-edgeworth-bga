<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects;

use Bga\Games\ProjectEdgeworth\Effects\Context\GameEvent;
use Bga\Games\ProjectEdgeworth\Effects\Context\StagedLinkRequest;

/**
 * 効果 1 回の解決の結果（011 §4-3）。エンジンが events を `collect()` して連鎖させ、logs は窓を閉じるとき通知にまとめる（007 §7-2）。
 */
final class Outcome
{
    /**
     * @param list<GameEvent> $events 起こしたイベント（連鎖。同じ窓のプールに加わる。007 §8-1）
     * @param list<LogEntry> $logs
     * @param list<StagedLinkRequest> $stagedLinks
     */
    public function __construct(
        public readonly array $events = [],
        public readonly array $logs = [],
        public readonly array $stagedLinks = [],
    ) {
    }

    public static function none(): self
    {
        return new self();
    }
}
