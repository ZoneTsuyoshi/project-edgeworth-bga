<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

/**
 * player テーブルの追加列 maya・budget・pad_cell・fame_detail のアクセサ（010 §7.1）。
 * player 行を丸ごと JSON にして返さない。fame_detail はゲーム終了時（121 以降）にだけ全員へ送る。
 */
final class PlayerValues
{
    public const INITIAL_BUDGET = 15;

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * 席順（player_no）のプレイヤー ID。
     *
     * @return list<int>
     */
    public function playerIds(): array
    {
        $rows = $this->db->rows('SELECT `player_id` FROM `player` ORDER BY `player_no` ASC');
        return array_map(static fn(array $r): int => (int) $r['player_id'], $rows);
    }

    public function maya(int $playerId): int
    {
        return (int) $this->column($playerId, 'maya');
    }

    /**
     * 所持マヤを直接書く（初期資金）。負は例外。
     */
    public function setMaya(int $playerId, int $value): void
    {
        $this->assertNonNegative('maya', $value);
        $this->update($playerId, "`maya` = $value");
    }

    /**
     * 所持マヤを増減する。結果が負になる場合は例外（マヤは負にならない。010 §12）。
     *
     * @return int 更新後の値
     */
    public function addMaya(int $playerId, int $delta): int
    {
        $value = $this->maya($playerId) + $delta;
        $this->assertNonNegative('maya', $value);
        $this->update($playerId, "`maya` = $value");
        return $value;
    }

    public function budget(int $playerId): int
    {
        return (int) $this->column($playerId, 'budget');
    }

    public function setBudget(int $playerId, int $value): void
    {
        $this->assertNonNegative('budget', $value);
        $this->update($playerId, "`budget` = $value");
    }

    /**
     * 予算を増減する。結果が負になる場合は例外。
     *
     * @return int 更新後の値
     */
    public function addBudget(int $playerId, int $delta): int
    {
        $value = $this->budget($playerId) + $delta;
        $this->assertNonNegative('budget', $value);
        $this->update($playerId, "`budget` = $value");
        return $value;
    }

    public function padCell(int $playerId): ?int
    {
        $v = $this->column($playerId, 'pad_cell');
        return $v === null ? null : (int) $v;
    }

    /**
     * 発射拠点を置く。他プレイヤーの発射拠点と同じ区域は例外（陸系の区域かどうかは呼び出し側の Board で検査する）。
     */
    public function setPadCell(int $playerId, ?int $cell): void
    {
        if ($cell !== null) {
            $taken = (int) $this->db->value(
                "SELECT COUNT(*) FROM `player` WHERE `pad_cell` = $cell AND `player_id` <> $playerId",
            );
            if ($taken > 0) {
                throw new \DomainException("cell $cell already has another player's launch base");
            }
        }
        $this->update($playerId, '`pad_cell` = ' . Sql::nullableInt($cell));
    }

    /**
     * 全員の発射拠点（置かれたものだけ）。公開情報。
     *
     * @return array<int, int> プレイヤー ID => 区域
     */
    public function padCells(): array
    {
        $map = [];
        $rows = $this->db->rows('SELECT `player_id`, `pad_cell` FROM `player` WHERE `pad_cell` IS NOT NULL ORDER BY `player_no`');
        foreach ($rows as $r) {
            $map[(int) $r['player_id']] = (int) $r['pad_cell'];
        }
        return $map;
    }

    /**
     * 名声の内訳（FameResult::$detail の形。010 §11.2）。未計算なら null。
     *
     * @return array<mixed>|null
     */
    public function fameDetail(int $playerId): ?array
    {
        return Sql::decode($this->column($playerId, 'fame_detail'));
    }

    /**
     * @param array<mixed>|null $detail
     */
    public function setFameDetail(int $playerId, ?array $detail): void
    {
        $this->update($playerId, '`fame_detail` = ' . Sql::nullableJson($this->db, $detail));
    }

    /**
     * 公開してよい値（マヤ・予算・発射拠点）。fame_detail は含めない。
     *
     * @return array<int, array{maya: int, budget: int, padCell: int|null}> プレイヤー ID => 値（席順）
     */
    public function publicValues(): array
    {
        $map = [];
        $rows = $this->db->rows('SELECT `player_id`, `maya`, `budget`, `pad_cell` FROM `player` ORDER BY `player_no`');
        foreach ($rows as $r) {
            $map[(int) $r['player_id']] = [
                'maya' => (int) $r['maya'],
                'budget' => (int) $r['budget'],
                'padCell' => $r['pad_cell'] === null ? null : (int) $r['pad_cell'],
            ];
        }
        return $map;
    }

    private function assertNonNegative(string $name, int $value): void
    {
        if ($value < 0) {
            throw new \DomainException("$name cannot be negative: $value");
        }
    }

    private function column(int $playerId, string $column): mixed
    {
        return $this->db->value("SELECT `$column` FROM `player` WHERE `player_id` = $playerId");
    }

    private function update(int $playerId, string $assignment): void
    {
        $this->db->execute("UPDATE `player` SET $assignment WHERE `player_id` = $playerId");
    }
}
