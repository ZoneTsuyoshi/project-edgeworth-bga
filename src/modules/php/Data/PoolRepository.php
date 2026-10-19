<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Data;

/**
 * pool テーブル = 効果の窓のプール（010 §9.5）。窓を閉じるとき（32）に clear() で全消去する。
 * 窓の問い（プロンプト）は保存せず、この表から getArgs のたびに組み立てる。
 */
final class PoolRepository
{
    private const COLUMNS = '`pool_id`, `pool_player`, `pool_window`, `pool_kind`, `pool_src`, `pool_effect`, `pool_event`, '
        . '`pool_context`, `pool_default_order`, `pool_chosen_order`, `pool_status`, `pool_state`';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * 効果をプールに入れ、行 ID を返す（状態は pending）。
     *
     * @param array<mixed>|null $context
     */
    public function add(
        int $playerId,
        string $window,
        string $kind,
        int $src,
        string $effect,
        string $event,
        ?array $context,
        int $defaultOrder,
    ): int {
        if ($kind !== 'P' && $kind !== 'S') {
            throw new \InvalidArgumentException("invalid pool kind: $kind");
        }
        $this->db->execute(
            'INSERT INTO `pool` (`pool_player`, `pool_window`, `pool_kind`, `pool_src`, `pool_effect`, `pool_event`, '
            . '`pool_context`, `pool_default_order`) VALUES ('
            . "$playerId, " . Sql::str($this->db, $window) . ", '$kind', $src, " . Sql::str($this->db, $effect) . ', '
            . Sql::str($this->db, $event) . ', ' . Sql::nullableJson($this->db, $context) . ", $defaultOrder)",
        );
        return $this->db->lastInsertId();
    }

    public function get(int $poolId): PoolEntry
    {
        $rows = $this->db->rows('SELECT ' . self::COLUMNS . " FROM `pool` WHERE `pool_id` = $poolId");
        if ($rows === []) {
            throw new \InvalidArgumentException("pool entry not found: $poolId");
        }
        return PoolEntry::fromRow($rows[0]);
    }

    /**
     * プレイヤーの項目。処理順（選んだ順があればそれ、なければデフォルト順。同順位は ID 順）に並べる。
     *
     * @return list<PoolEntry>
     */
    public function forPlayer(int $playerId, ?PoolStatus $status = null): array
    {
        $where = "WHERE `pool_player` = $playerId" . ($status === null ? '' : " AND `pool_status` = '{$status->value}'");
        return $this->fetch($where . ' ORDER BY COALESCE(`pool_chosen_order`, `pool_default_order`) ASC, `pool_id` ASC');
    }

    /**
     * @return list<PoolEntry>
     */
    public function all(): array
    {
        return $this->fetch('ORDER BY `pool_player` ASC, COALESCE(`pool_chosen_order`, `pool_default_order`) ASC, `pool_id` ASC');
    }

    public function countByStatus(PoolStatus $status, ?int $playerId = null): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM `pool` WHERE `pool_status` = '{$status->value}'"
            . ($playerId === null ? '' : " AND `pool_player` = $playerId"),
        );
    }

    public function count(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM `pool`');
    }

    public function setStatus(int $poolId, PoolStatus $status): void
    {
        $this->db->execute("UPDATE `pool` SET `pool_status` = '{$status->value}' WHERE `pool_id` = $poolId");
    }

    /**
     * プレイヤーが選んだ順番を書く。確定後は変えられない（009 未確定 1）。
     */
    public function setChosenOrder(int $poolId, int $order): void
    {
        if ($this->get($poolId)->chosenOrder !== null) {
            throw new \DomainException("order of pool entry $poolId is already fixed");
        }
        $this->db->execute("UPDATE `pool` SET `pool_chosen_order` = $order WHERE `pool_id` = $poolId");
    }

    /**
     * @param array<mixed>|null $state
     */
    public function setState(int $poolId, ?array $state): void
    {
        $this->db->execute(
            'UPDATE `pool` SET `pool_state` = ' . Sql::nullableJson($this->db, $state) . " WHERE `pool_id` = $poolId",
        );
    }

    /**
     * 窓を閉じるときの全消去。消した後に 0 件であることを確かめる。
     */
    public function clear(): void
    {
        $this->db->execute('DELETE FROM `pool`');
        if ($this->count() !== 0) {
            throw new \LogicException('pool is not empty after clear');
        }
    }

    /**
     * @return list<PoolEntry>
     */
    private function fetch(string $suffix): array
    {
        return array_map(
            static fn(array $row): PoolEntry => PoolEntry::fromRow($row),
            $this->db->rows('SELECT ' . self::COLUMNS . ' FROM `pool` ' . $suffix),
        );
    }
}
