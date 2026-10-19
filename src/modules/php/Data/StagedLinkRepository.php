<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Data;

/**
 * staged_link テーブルの読み書きと予約の計算（010 §5.3）。他プレイヤーには見せない（バリアまで）。
 */
final class StagedLinkRepository
{
    private const COLUMNS = '`stage_id`, `stage_player`, `stage_cell`, `stage_sat`, `stage_special`, `stage_source`, '
        . '`stage_area_cost`, `stage_special_cost`, `stage_pioneer`, `stage_reserved`';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * 通信確立をステージに積み、行 ID を返す。予約額は区域コスト + 特殊コスト + 先駆コストの合計。
     * キューブが残っていない（16 − link − staged ≦ 0）、すでに通信確立済み・ステージ済みの区域は例外。
     * マヤの不足は呼び出し側が availableMaya() で確認する。
     *
     * @param list<array{to: int, amount: int}> $pioneer
     */
    public function stage(
        int $playerId,
        int $cell,
        int $satId,
        bool $special,
        string $source,
        int $areaCost,
        int $specialCost,
        array $pioneer,
    ): int {
        if ($this->remainingCubes($playerId) <= 0) {
            throw new \DomainException("player $playerId has no cubes left to stage");
        }
        $linked = (int) $this->db->value(
            "SELECT COUNT(*) FROM `link` WHERE `link_player` = $playerId AND `link_cell` = $cell",
        );
        $staged = (int) $this->db->value(
            "SELECT COUNT(*) FROM `staged_link` WHERE `stage_player` = $playerId AND `stage_cell` = $cell",
        );
        if ($linked + $staged > 0) {
            throw new \DomainException("player $playerId already has a link or a staged link at cell $cell");
        }
        $reserved = $areaCost + $specialCost + array_sum(array_column($pioneer, 'amount'));
        $this->db->execute(
            'INSERT INTO `staged_link` (`stage_player`, `stage_cell`, `stage_sat`, `stage_special`, `stage_source`, '
            . '`stage_area_cost`, `stage_special_cost`, `stage_pioneer`, `stage_reserved`) VALUES ('
            . "$playerId, $cell, $satId, " . ($special ? 1 : 0) . ', ' . Sql::str($this->db, $source)
            . ", $areaCost, $specialCost, " . Sql::json($this->db, $pioneer) . ", $reserved)",
        );
        return $this->db->lastInsertId();
    }

    /**
     * @return list<StagedLink> 積んだ順
     */
    public function all(): array
    {
        return $this->fetch('ORDER BY `stage_id` ASC');
    }

    /**
     * @return list<StagedLink> 積んだ順
     */
    public function forPlayer(int $playerId): array
    {
        return $this->fetch("WHERE `stage_player` = $playerId ORDER BY `stage_id` ASC");
    }

    public function count(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM `staged_link`');
    }

    public function reservedMaya(int $playerId): int
    {
        return (int) $this->db->value(
            "SELECT COALESCE(SUM(`stage_reserved`), 0) FROM `staged_link` WHERE `stage_player` = $playerId",
        );
    }

    /**
     * 利用できるマヤ = 所持マヤ − 予約額。
     */
    public function availableMaya(int $playerId): int
    {
        $maya = (int) $this->db->value("SELECT `maya` FROM `player` WHERE `player_id` = $playerId");
        return $maya - $this->reservedMaya($playerId);
    }

    /**
     * 利用できるキューブ = 16 − link − staged。
     */
    public function remainingCubes(int $playerId): int
    {
        $linked = (int) $this->db->value("SELECT COUNT(*) FROM `link` WHERE `link_player` = $playerId");
        $staged = (int) $this->db->value("SELECT COUNT(*) FROM `staged_link` WHERE `stage_player` = $playerId");
        return LinkRepository::CUBES_PER_PLAYER - $linked - $staged;
    }

    public function remove(int $stageId): void
    {
        $this->db->execute("DELETE FROM `staged_link` WHERE `stage_id` = $stageId");
    }

    public function clear(): void
    {
        $this->db->execute('DELETE FROM `staged_link`');
    }

    /**
     * @return list<StagedLink>
     */
    private function fetch(string $suffix): array
    {
        return array_map(
            static fn(array $row): StagedLink => StagedLink::fromRow($row),
            $this->db->rows('SELECT ' . self::COLUMNS . ' FROM `staged_link` ' . $suffix),
        );
    }
}
