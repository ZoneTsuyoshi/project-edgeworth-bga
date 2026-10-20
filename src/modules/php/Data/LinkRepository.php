<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

use Bga\Games\projectedgeworth\Board\CubeSlots;

/**
 * link テーブルの読み書き（010 §5.2）。放棄したキューブは行を作らない。発射拠点は入れない（player.pad_cell）。
 */
final class LinkRepository
{
    public const CUBES_PER_PLAYER = 16;

    private const COLUMNS = '`link_id`, `link_player`, `link_cell`, `link_slot`, `link_level`, `link_commit`, `link_round`';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * 通信確立を 1 回のコミットとして一斉に置く。
     * 同じコミットで同じ区域に置くキューブは同じスロット n（= コミット前にその区域にあった数）で、段を 0, 1, 2… と席順（配列順）に割り当てる。
     * 1 人 16 個まで・同じ区域に自分は 1 つまで・スロットは 0〜3 まで。違反すると例外（呼び出し側の DB トランザクションで巻き戻す）。
     *
     * @param list<array{playerId: int, cell: int}> $placements 席順
     * @return list<Link> 置いた順
     */
    public function commit(array $placements, int $round, int $commitNo): array
    {
        $before = [];
        foreach ($placements as $p) {
            $before[$p['cell']] ??= $this->countInCell($p['cell']);
        }
        $levels = [];
        $ids = [];
        foreach ($placements as $p) {
            $cell = $p['cell'];
            $slot = CubeSlots::nextSlot($before[$cell])
                ?? throw new \DomainException("cell $cell has no free cube slot");
            if ($this->countFor($p['playerId']) >= self::CUBES_PER_PLAYER) {
                throw new \DomainException("player {$p['playerId']} has no cubes left");
            }
            $level = $levels[$cell] ?? 0;
            $levels[$cell] = $level + 1;
            $this->db->execute(
                'INSERT INTO `link` (`link_player`, `link_cell`, `link_slot`, `link_level`, `link_commit`, `link_round`) VALUES ('
                . "{$p['playerId']}, $cell, $slot, $level, $commitNo, $round)",
            );
            $ids[] = $this->db->lastInsertId();
        }
        return array_map(fn(int $id): Link => $this->get($id), $ids);
    }

    public function get(int $linkId): Link
    {
        $rows = $this->db->rows('SELECT ' . self::COLUMNS . " FROM `link` WHERE `link_id` = $linkId");
        if ($rows === []) {
            throw new \InvalidArgumentException("link not found: $linkId");
        }
        return Link::fromRow($rows[0]);
    }

    /**
     * @return list<Link> コミット順、スロット、段の順
     */
    public function all(): array
    {
        return $this->fetch('ORDER BY `link_commit` ASC, `link_slot` ASC, `link_level` ASC, `link_id` ASC');
    }

    /**
     * @return list<Link>
     */
    public function forPlayer(int $playerId): array
    {
        return $this->fetch("WHERE `link_player` = $playerId ORDER BY `link_id` ASC");
    }

    /**
     * @return list<Link> スロット、段の順
     */
    public function inCell(int $cell): array
    {
        return $this->fetch("WHERE `link_cell` = $cell ORDER BY `link_slot` ASC, `link_level` ASC");
    }

    /**
     * 通信確立数（発射拠点を含めない。001 §10・§11-5）。
     */
    public function countFor(int $playerId): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM `link` WHERE `link_player` = $playerId");
    }

    public function countInCell(int $cell): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM `link` WHERE `link_cell` = $cell");
    }

    /**
     * @return list<int> 区域 ID の昇順
     */
    public function cellsFor(int $playerId): array
    {
        $rows = $this->db->rows("SELECT `link_cell` FROM `link` WHERE `link_player` = $playerId ORDER BY `link_cell` ASC");
        return array_map(static fn(array $r): int => (int) $r['link_cell'], $rows);
    }

    public function hasLink(int $playerId, int $cell): bool
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM `link` WHERE `link_player` = $playerId AND `link_cell` = $cell",
        ) > 0;
    }

    /**
     * @return list<Link>
     */
    private function fetch(string $suffix): array
    {
        return array_map(
            static fn(array $row): Link => Link::fromRow($row),
            $this->db->rows('SELECT ' . self::COLUMNS . ' FROM `link` ' . $suffix),
        );
    }
}
