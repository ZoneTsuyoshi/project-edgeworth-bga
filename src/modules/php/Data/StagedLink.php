<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

/**
 * staged_link テーブルの 1 行 = 窓の中で積まれた、まだコミットされていない通信確立（010 §5.3）。
 */
final class StagedLink
{
    /**
     * @param list<array{to: int, amount: int}> $pioneer 先駆コストの内訳
     */
    public function __construct(
        public readonly int $id,
        public readonly int $playerId,
        public readonly int $cell,
        public readonly int $satId,
        public readonly bool $special,
        public readonly string $source,
        public readonly int $areaCost,
        public readonly int $specialCost,
        public readonly array $pioneer,
        public readonly int $reserved,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $pioneer = [];
        foreach (Sql::decode($row['stage_pioneer']) ?? [] as $entry) {
            if (is_array($entry)) {
                $pioneer[] = ['to' => (int) ($entry['to'] ?? 0), 'amount' => (int) ($entry['amount'] ?? 0)];
            }
        }
        return new self(
            (int) $row['stage_id'],
            (int) $row['stage_player'],
            (int) $row['stage_cell'],
            (int) $row['stage_sat'],
            (int) $row['stage_special'] === 1,
            (string) $row['stage_source'],
            (int) $row['stage_area_cost'],
            (int) $row['stage_special_cost'],
            $pioneer,
            (int) $row['stage_reserved'],
        );
    }
}
