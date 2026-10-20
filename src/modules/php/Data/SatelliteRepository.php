<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Material\SatelliteColor;
use Bga\Games\projectedgeworth\Material\SatellitePlan;
use Bga\Games\projectedgeworth\Material\SatelliteShape;

/**
 * satellite テーブルの読み書き（010 §5.1）。稼働・非稼働の遷移は cell・dir・fuel_used を同時に書く。
 * 色・形状・プランは enum の宣言順を整数で保存する（色 BLUE=0… / 形状 HAMMER=0… / プラン MOON=0…。010 §5.1）。
 */
final class SatelliteRepository
{
    private const COLUMNS = '`sat_id`, `sat_player`, `sat_color`, `sat_shape`, `sat_plan`, `sat_card`, `sat_cell`, `sat_dir`, '
        . '`sat_fuel_used`, `sat_grace`, `sat_activation`';

    public function __construct(private readonly Database $db)
    {
    }

    public static function colorFromInt(int $value): SatelliteColor
    {
        return SatelliteColor::cases()[$value] ?? throw new \InvalidArgumentException("invalid color: $value");
    }

    public static function shapeFromInt(int $value): SatelliteShape
    {
        return SatelliteShape::cases()[$value] ?? throw new \InvalidArgumentException("invalid shape: $value");
    }

    public static function planFromInt(int $value): SatellitePlan
    {
        return SatellitePlan::cases()[$value] ?? throw new \InvalidArgumentException("invalid plan: $value");
    }

    public static function colorToInt(SatelliteColor $color): int
    {
        return (int) array_search($color, SatelliteColor::cases(), true);
    }

    public static function shapeToInt(SatelliteShape $shape): int
    {
        return (int) array_search($shape, SatelliteShape::cases(), true);
    }

    public static function planToInt(SatellitePlan $plan): int
    {
        return (int) array_search($plan, SatellitePlan::cases(), true);
    }

    /**
     * 衛星を非稼働で 1 機作る（setupNewGame 用）。(playerId, shape) が重複すると例外。
     */
    public function create(
        int $playerId,
        SatelliteColor $color,
        SatelliteShape $shape,
        SatellitePlan $plan,
        string $cardId,
    ): Satellite {
        $this->db->execute(
            'INSERT INTO `satellite` (`sat_player`, `sat_color`, `sat_shape`, `sat_plan`, `sat_card`) VALUES ('
            . "$playerId, " . self::colorToInt($color) . ', ' . self::shapeToInt($shape) . ', '
            . self::planToInt($plan) . ', ' . Sql::str($this->db, $cardId) . ')',
        );
        return $this->get($this->db->lastInsertId());
    }

    public function get(int $satId): Satellite
    {
        $rows = $this->db->rows('SELECT ' . self::COLUMNS . " FROM `satellite` WHERE `sat_id` = $satId");
        if ($rows === []) {
            throw new \InvalidArgumentException("satellite not found: $satId");
        }
        return Satellite::fromRow($rows[0]);
    }

    /**
     * 形状の順（007 §6-3 のデフォルト順）で。
     *
     * @return list<Satellite>
     */
    public function forPlayer(int $playerId): array
    {
        return $this->fetch("WHERE `sat_player` = $playerId ORDER BY `sat_shape` ASC");
    }

    /**
     * @return list<Satellite>
     */
    public function all(): array
    {
        return $this->fetch('ORDER BY `sat_player` ASC, `sat_shape` ASC');
    }

    /**
     * 稼働中でその区域にある衛星。
     *
     * @return list<Satellite>
     */
    public function activeAtCell(int $cell): array
    {
        return $this->fetch("WHERE `sat_cell` = $cell ORDER BY `sat_id` ASC");
    }

    /**
     * 非稼働の衛星を打ち上げる（cell・dir を書く。燃料は 0 のまま）。向きは 6 方向（003 §4.1）。
     */
    public function launch(int $satId, int $cell, Direction $direction): void
    {
        $sat = $this->get($satId);
        if ($sat->isActive()) {
            throw new \DomainException("satellite $satId is already active");
        }
        $this->db->execute(
            "UPDATE `satellite` SET `sat_cell` = $cell, `sat_dir` = '{$direction->value}', `sat_fuel_used` = 0 "
            . "WHERE `sat_id` = $satId",
        );
    }

    /**
     * 稼働中の衛星を動かす（区域と向きを同時に更新）。
     */
    public function move(int $satId, int $cell, Direction $direction): void
    {
        $this->assertActive($satId);
        $this->db->execute(
            "UPDATE `satellite` SET `sat_cell` = $cell, `sat_dir` = '{$direction->value}' WHERE `sat_id` = $satId",
        );
    }

    /**
     * 向きだけを変える（転回）。
     */
    public function turn(int $satId, Direction $direction): void
    {
        $this->assertActive($satId);
        $this->db->execute("UPDATE `satellite` SET `sat_dir` = '{$direction->value}' WHERE `sat_id` = $satId");
    }

    /**
     * 非稼働に戻す（着陸・着水・燃料切れなど）。cell・dir を NULL、燃料消費を 0 にする。
     */
    public function deactivate(int $satId): void
    {
        $this->db->execute(
            "UPDATE `satellite` SET `sat_cell` = NULL, `sat_dir` = NULL, `sat_fuel_used` = 0 WHERE `sat_id` = $satId",
        );
    }

    /**
     * 燃料の消費量を増減する。0 〜 容量 + 1 に丸める（001 §4-4）。非稼働では変えられない。
     *
     * @param int $capacity 燃料容量 C（派生値。呼び出し側が計算して渡す）
     * @return int 更新後の消費量
     */
    public function addFuelUsed(int $satId, int $delta, int $capacity): int
    {
        $sat = $this->assertActive($satId);
        $value = max(0, min($capacity + 1, $sat->fuelUsed + $delta));
        $this->db->execute("UPDATE `satellite` SET `sat_fuel_used` = $value WHERE `sat_id` = $satId");
        return $value;
    }

    /**
     * 2 機の位置・向き・燃料消費量を交換する（P024。007 §8-2）。
     */
    public function swapPositions(int $satIdA, int $satIdB): void
    {
        $a = $this->get($satIdA);
        $b = $this->get($satIdB);
        $this->writeState($satIdA, $b);
        $this->writeState($satIdB, $a);
    }

    public function setGrace(int $satId, bool $grace): void
    {
        $this->db->execute('UPDATE `satellite` SET `sat_grace` = ' . ($grace ? 1 : 0) . " WHERE `sat_id` = $satId");
    }

    /**
     * 全衛星の着陸猶予を外す（循環フェイズの終わり。009 §7）。
     */
    public function clearAllGrace(): void
    {
        $this->db->execute('UPDATE `satellite` SET `sat_grace` = 0');
    }

    public function setActivation(int $satId, int $value): void
    {
        $this->db->execute("UPDATE `satellite` SET `sat_activation` = $value WHERE `sat_id` = $satId");
    }

    public function resetActivations(): void
    {
        $this->db->execute('UPDATE `satellite` SET `sat_activation` = 0');
    }

    /**
     * @return array<mixed>|null
     */
    public function memo(int $satId): ?array
    {
        return Sql::decode($this->db->value("SELECT `sat_memo` FROM `satellite` WHERE `sat_id` = $satId"));
    }

    /**
     * @param array<mixed>|null $memo
     */
    public function setMemo(int $satId, ?array $memo): void
    {
        $this->db->execute(
            'UPDATE `satellite` SET `sat_memo` = ' . Sql::nullableJson($this->db, $memo) . " WHERE `sat_id` = $satId",
        );
    }

    private function writeState(int $satId, Satellite $from): void
    {
        $this->db->execute(
            'UPDATE `satellite` SET `sat_cell` = ' . Sql::nullableInt($from->cell)
            . ', `sat_dir` = ' . Sql::nullableStr($this->db, $from->direction?->value)
            . ", `sat_fuel_used` = {$from->fuelUsed} WHERE `sat_id` = $satId",
        );
    }

    private function assertActive(int $satId): Satellite
    {
        $sat = $this->get($satId);
        if (!$sat->isActive()) {
            throw new \DomainException("satellite $satId is not active");
        }
        return $sat;
    }

    /**
     * @return list<Satellite>
     */
    private function fetch(string $suffix): array
    {
        return array_map(
            static fn(array $row): Satellite => Satellite::fromRow($row),
            $this->db->rows('SELECT ' . self::COLUMNS . ' FROM `satellite` ' . $suffix),
        );
    }
}
