<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

/**
 * held_notif テーブル（窓の保留通知。010 §10.5）。バリア（32）で held_id の昇順に全員へ送り、clear() で空にする。
 * 送信そのもの・席順でのまとめ・圧縮は通知を送る側（Flow/Notification）の仕事で、ここは保存と読み出しだけ。
 */
final class HeldNotificationRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * 保留に積み、held_id を返す。
     *
     * @param array<mixed> $args
     */
    public function push(int $originPlayerId, string $type, string $variant, array $args): int
    {
        $this->db->execute(
            'INSERT INTO `held_notif` (`held_origin`, `held_type`, `held_variant`, `held_args`) VALUES ('
            . "$originPlayerId, " . Sql::str($this->db, $type) . ', ' . Sql::str($this->db, $variant) . ', '
            . Sql::json($this->db, $args) . ')',
        );
        return $this->db->lastInsertId();
    }

    /**
     * 時系列（held_id の昇順）で。
     *
     * @return list<HeldNotification>
     */
    public function all(): array
    {
        $rows = $this->db->rows(
            'SELECT `held_id`, `held_origin`, `held_type`, `held_variant`, `held_args` FROM `held_notif` ORDER BY `held_id` ASC',
        );
        return array_map(static fn(array $r): HeldNotification => new HeldNotification(
            (int) $r['held_id'],
            (int) $r['held_origin'],
            (string) $r['held_type'],
            (string) $r['held_variant'],
            Sql::decode($r['held_args']) ?? [],
        ), $rows);
    }

    public function count(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM `held_notif`');
    }

    /**
     * 起点のプレイヤーの分だけ捨てる（II 配備の [Reset]）。
     */
    public function clearOrigin(int $originPlayerId): void
    {
        $this->db->execute("DELETE FROM `held_notif` WHERE `held_origin` = $originPlayerId");
    }

    /**
     * 起点のプレイヤーごとの最初の held_id（ステージした順を知るため）。
     *
     * @return array<int, int> プレイヤー ID => 最小の held_id
     */
    public function firstIdByOrigin(): array
    {
        $out = [];
        foreach ($this->db->rows('SELECT `held_origin`, MIN(`held_id`) AS `first_id` FROM `held_notif` GROUP BY `held_origin`') as $row) {
            $out[(int) $row['held_origin']] = (int) $row['first_id'];
        }

        return $out;
    }

    public function clear(): void
    {
        $this->db->execute('DELETE FROM `held_notif`');
    }
}
