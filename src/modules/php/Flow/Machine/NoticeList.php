<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

/**
 * ドライバが 1 回の入場の間に集める通知。null は無視する（増減 0 の通知を作らない部品の戻り値をそのまま渡せる）。
 */
final class NoticeList
{
    /** @var list<Notice> */
    private array $items = [];

    public function add(?Notice ...$notices): void
    {
        foreach ($notices as $notice) {
            if ($notice !== null) {
                $this->items[] = $notice;
            }
        }
    }

    /**
     * @param iterable<Notice|null> $notices
     */
    public function addAll(iterable $notices): void
    {
        foreach ($notices as $notice) {
            $this->add($notice);
        }
    }

    /**
     * @return list<Notice>
     */
    public function all(): array
    {
        return $this->items;
    }
}
