<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Data;

/**
 * choice テーブル = 同時選択の確定内容（010 §9.2・§9.3）。全員確定まで非公開。
 *
 * 公開側に出せるのは confirmedPlayers()（ID だけ）。内容を返すメソッドは、本人の視点（ownChoice）か、
 * 全員確定後にサーバーが一斉に処理するとき（revealAll）に限る。choice_auto は公開側に出さない。
 */
final class ChoiceRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * 状態に入るとき、またはコミットが終わったあとに、その状態の行を全消去する。
     */
    public function clear(int $state): void
    {
        $this->db->execute("DELETE FROM `choice` WHERE `choice_state` = $state");
    }

    /**
     * 選択を確定する。同じプレイヤーの再確定は上書きして version を +1 する。参加者以外は例外。
     *
     * @param array<mixed> $data
     * @param list<int> $participants 状態の参加者（step_participants）
     * @param bool $auto サーバーが作った [Pass] 相当の行か
     */
    public function submit(int $playerId, int $state, array $data, array $participants, bool $auto = false): void
    {
        if (!in_array($playerId, $participants, true)) {
            throw new \DomainException("player $playerId is not a participant of state $state");
        }
        $this->db->execute(
            'INSERT INTO `choice` (`choice_player`, `choice_state`, `choice_data`, `choice_auto`, `choice_version`) VALUES ('
            . "$playerId, $state, " . Sql::json($this->db, $data) . ', ' . ($auto ? 1 : 0) . ', 1) '
            . 'ON DUPLICATE KEY UPDATE `choice_data` = VALUES(`choice_data`), `choice_auto` = VALUES(`choice_auto`), '
            . '`choice_version` = `choice_version` + 1',
        );
    }

    /**
     * 確定済みのプレイヤー ID（昇順）。公開してよい。
     *
     * @return list<int>
     */
    public function confirmedPlayers(int $state): array
    {
        $rows = $this->db->rows("SELECT `choice_player` FROM `choice` WHERE `choice_state` = $state ORDER BY `choice_player`");
        return array_map(static fn(array $r): int => (int) $r['choice_player'], $rows);
    }

    public function isConfirmed(int $playerId, int $state): bool
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM `choice` WHERE `choice_player` = $playerId AND `choice_state` = $state",
        ) > 0;
    }

    /**
     * 全員確定したか。判定は DB の行数で 1 回だけ行う（009 §5.2）。
     *
     * @param list<int> $participants
     */
    public function allConfirmed(int $state, array $participants): bool
    {
        if ($participants === []) {
            return true;
        }
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM `choice` WHERE `choice_state` = $state AND `choice_player` IN (" . Sql::intList($participants) . ')',
        ) === count(array_unique($participants));
    }

    /**
     * 本人の確定内容。本人の視点でだけ呼ぶ。
     *
     * @return array<mixed>|null
     */
    public function ownChoice(int $playerId, int $state): ?array
    {
        return Sql::decode($this->db->value(
            "SELECT `choice_data` FROM `choice` WHERE `choice_player` = $playerId AND `choice_state` = $state",
        ));
    }

    /**
     * 全員の確定内容（公開 23 などで一斉に処理するときだけ）。公開側へ返してはならない。
     *
     * @return array<int, array<mixed>> プレイヤー ID => 内容
     */
    public function revealAll(int $state): array
    {
        $map = [];
        $rows = $this->db->rows(
            "SELECT `choice_player`, `choice_data` FROM `choice` WHERE `choice_state` = $state ORDER BY `choice_player`",
        );
        foreach ($rows as $r) {
            $map[(int) $r['choice_player']] = Sql::decode($r['choice_data']) ?? [];
        }
        return $map;
    }

    /**
     * サーバーが作った [Pass] 相当の行か（ログの理由用。公開側に出さない）。
     */
    public function isAuto(int $playerId, int $state): bool
    {
        return (int) $this->db->value(
            "SELECT `choice_auto` FROM `choice` WHERE `choice_player` = $playerId AND `choice_state` = $state",
        ) === 1;
    }

    public function version(int $playerId, int $state): int
    {
        return (int) $this->db->value(
            "SELECT `choice_version` FROM `choice` WHERE `choice_player` = $playerId AND `choice_state` = $state",
        );
    }
}
