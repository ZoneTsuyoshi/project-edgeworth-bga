<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects;

use Bga\Games\projectedgeworth\Effects\Context\GameEvent;

/**
 * `EffectEngine::resolveImmediate()` の結果（窓の外で 1 人分のプールを解決した結果。009 追補 4）。
 */
final class ImmediateResult
{
    /**
     * @param list<GameEvent> $events 起きたイベント（渡したイベントと、効果の連鎖で起きたイベント）。`data.resolved` に、
     *        そのイベントで解決済み（使用済み・対象なしで捨てた・再確認で落ちた）の宿主の鍵を持つ。窓 DE が飛ばすのに使う
     * @param list<LogEntry> $logs 解決した効果のログ
     * @param int $resolved 解決した効果の数
     * @param int $left 選択が要る・順序の問いが要るため窓 DE に残した効果の数
     */
    public function __construct(
        public readonly array $events,
        public readonly array $logs,
        public readonly int $resolved,
        public readonly int $left,
    ) {
    }
}
