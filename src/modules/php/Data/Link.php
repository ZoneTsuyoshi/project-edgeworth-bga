<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Data;

/**
 * link テーブルの 1 行 = 置かれたキューブ 1 個（010 §5.2）。
 */
final class Link
{
    public function __construct(
        public readonly int $id,
        public readonly int $playerId,
        public readonly int $cell,
        public readonly int $slot,
        public readonly int $level,
        public readonly int $commit,
        public readonly int $round,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['link_id'],
            (int) $row['link_player'],
            (int) $row['link_cell'],
            (int) $row['link_slot'],
            (int) $row['link_level'],
            (int) $row['link_commit'],
            (int) $row['link_round'],
        );
    }
}
