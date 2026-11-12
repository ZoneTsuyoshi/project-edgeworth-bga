<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

/**
 * deploy_stage テーブルの読み書き（010 追補 2026-11-12）。主キーは player_id。61 を出るとき（バリアのコミットの後）に全行を消す。
 * 他プレイヤーには見せない（バリアまで）。choice（確定一覧）とは別に持つ。
 */
final class DeployStageRepository
{
    private const COLUMNS = '`ds_player`, `ds_card`, `ds_sat`, `ds_tier`, `ds_paid`, `ds_sold`, `ds_irreversible`, '
        . '`ds_snapshot`, `ds_public_before`, `ds_resolved`';

    public function __construct(private readonly Database $db)
    {
    }

    public function save(DeployStage $stage): void
    {
        $this->db->execute(
            'REPLACE INTO `deploy_stage` (' . self::COLUMNS . ') VALUES ('
            . "{$stage->playerId}, {$stage->card}, {$stage->satId}, {$stage->tier}, {$stage->paid}, "
            . Sql::json($this->db, $stage->sold) . ', ' . ($stage->irreversible ? 1 : 0) . ', '
            . Sql::json($this->db, $stage->snapshot) . ', ' . Sql::json($this->db, $stage->publicBefore) . ', '
            . Sql::json($this->db, $stage->resolved) . ')',
        );
    }

    public function find(int $playerId): ?DeployStage
    {
        $rows = $this->db->rows('SELECT ' . self::COLUMNS . " FROM `deploy_stage` WHERE `ds_player` = $playerId");

        return $rows === [] ? null : self::fromRow($rows[0]);
    }

    public function isStaged(int $playerId): bool
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM `deploy_stage` WHERE `ds_player` = $playerId") > 0;
    }

    /**
     * @return list<DeployStage> プレイヤー ID の昇順
     */
    public function all(): array
    {
        return array_map(
            static fn(array $row): DeployStage => self::fromRow($row),
            $this->db->rows('SELECT ' . self::COLUMNS . ' FROM `deploy_stage` ORDER BY `ds_player` ASC'),
        );
    }

    public function markIrreversible(int $playerId): void
    {
        $this->db->execute("UPDATE `deploy_stage` SET `ds_irreversible` = 1 WHERE `ds_player` = $playerId");
    }

    public function remove(int $playerId): void
    {
        $this->db->execute("DELETE FROM `deploy_stage` WHERE `ds_player` = $playerId");
    }

    public function clear(): void
    {
        $this->db->execute('DELETE FROM `deploy_stage`');
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fromRow(array $row): DeployStage
    {
        return new DeployStage(
            (int) $row['ds_player'],
            (int) $row['ds_card'],
            (int) $row['ds_sat'],
            (int) $row['ds_tier'],
            (int) $row['ds_paid'],
            array_values(array_map('intval', Sql::decode($row['ds_sold']) ?? [])),
            (int) $row['ds_irreversible'] === 1,
            Sql::decode($row['ds_snapshot']) ?? [],
            Sql::decode($row['ds_public_before']) ?? [],
            array_values(Sql::decode($row['ds_resolved']) ?? []),
        );
    }
}
