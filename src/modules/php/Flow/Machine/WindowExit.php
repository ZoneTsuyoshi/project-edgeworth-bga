<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

/**
 * 効果の窓を閉じた結果（32）: 戻り先の状態 ID と、送る通知。`resume` が null なら窓は閉じず、選択が要る人がいるので 31 へ戻る（'again'）。
 */
final class WindowExit
{
    /**
     * @param list<Notice> $notices
     */
    public function __construct(
        public readonly ?int $resume,
        public readonly array $notices,
    ) {
    }
}
