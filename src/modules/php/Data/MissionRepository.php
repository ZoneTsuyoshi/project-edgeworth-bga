<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

/**
 * mission_card テーブル（10 行。010 §6.2）。本人以外には番号を送らない。配られなかったカードの存在も送らない。
 */
final class MissionRepository
{
    public const CARD_COUNT = 10;
    public const PER_PLAYER = 2;

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * 10 枚を持ち主なしで作る（setupNewGame 用）。
     */
    public function createAll(): void
    {
        $values = [];
        for ($no = 1; $no <= self::CARD_COUNT; $no++) {
            $values[] = "($no)";
        }
        $this->db->execute('INSERT INTO `mission_card` (`mission_no`) VALUES ' . implode(', ', $values));
    }

    /**
     * プレイヤーに 2 枚を配る。重複・配布済みのカード・枚数違いは例外。
     *
     * @param list<int> $missionNos
     */
    public function deal(int $playerId, array $missionNos): void
    {
        if (count($missionNos) !== self::PER_PLAYER || count(array_unique($missionNos)) !== self::PER_PLAYER) {
            throw new \InvalidArgumentException('exactly ' . self::PER_PLAYER . ' distinct missions must be dealt');
        }
        if ($this->forPlayer($playerId) !== []) {
            throw new \DomainException("player $playerId already has missions");
        }
        foreach ($missionNos as $no) {
            $owner = $this->db->rows("SELECT `mission_player` FROM `mission_card` WHERE `mission_no` = $no");
            if ($owner === []) {
                throw new \InvalidArgumentException("mission not found: $no");
            }
            if ($owner[0]['mission_player'] !== null) {
                throw new \DomainException("mission $no is already dealt");
            }
        }
        $this->db->execute(
            "UPDATE `mission_card` SET `mission_player` = $playerId WHERE `mission_no` IN (" . Sql::intList($missionNos) . ')',
        );
    }

    /**
     * 本人のミッション番号（昇順）。本人の視点でだけ使う。
     *
     * @return list<int>
     */
    public function forPlayer(int $playerId): array
    {
        $rows = $this->db->rows("SELECT `mission_no` FROM `mission_card` WHERE `mission_player` = $playerId ORDER BY `mission_no`");
        return array_map(static fn(array $r): int => (int) $r['mission_no'], $rows);
    }

    /**
     * 配られなかった（使わない）カードの番号。サーバー内部専用で、クライアントには送らない。
     *
     * @return list<int>
     */
    public function undealt(): array
    {
        $rows = $this->db->rows('SELECT `mission_no` FROM `mission_card` WHERE `mission_player` IS NULL ORDER BY `mission_no`');
        return array_map(static fn(array $r): int => (int) $r['mission_no'], $rows);
    }

    /**
     * 全員のミッション。終了時の名声計算（121）以降だけ使う。
     *
     * @return array<int, list<int>> プレイヤー ID => 番号
     */
    public function allDealt(): array
    {
        $map = [];
        $rows = $this->db->rows(
            'SELECT `mission_player`, `mission_no` FROM `mission_card` WHERE `mission_player` IS NOT NULL '
            . 'ORDER BY `mission_player`, `mission_no`',
        );
        foreach ($rows as $r) {
            $map[(int) $r['mission_player']][] = (int) $r['mission_no'];
        }
        return $map;
    }
}
