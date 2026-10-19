<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Data;

/**
 * pool テーブルの 1 行 = 窓の中でトリガーした効果 1 件（010 §9.5）。context・state の中身は 011 が定める。
 */
final class PoolEntry
{
    /**
     * @param string $kind 'P' = プロジェクトカード（src = card_id）、'S' = 衛星カード（src = sat_id）
     * @param array<mixed>|null $context
     * @param array<mixed>|null $state
     */
    public function __construct(
        public readonly int $id,
        public readonly int $playerId,
        public readonly string $window,
        public readonly string $kind,
        public readonly int $src,
        public readonly string $effect,
        public readonly string $event,
        public readonly ?array $context,
        public readonly int $defaultOrder,
        public readonly ?int $chosenOrder,
        public readonly PoolStatus $status,
        public readonly ?array $state,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['pool_id'],
            (int) $row['pool_player'],
            (string) $row['pool_window'],
            (string) $row['pool_kind'],
            (int) $row['pool_src'],
            (string) $row['pool_effect'],
            (string) $row['pool_event'],
            Sql::decode($row['pool_context']),
            (int) $row['pool_default_order'],
            $row['pool_chosen_order'] === null ? null : (int) $row['pool_chosen_order'],
            PoolStatus::from((string) $row['pool_status']),
            Sql::decode($row['pool_state']),
        );
    }
}
