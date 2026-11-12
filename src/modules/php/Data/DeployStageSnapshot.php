<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

/**
 * [Reset]（009 追補 5）のための、1 人分のステージ前のスナップショットと復元。個々の効果を逆に実行する方式は取らない。
 *
 * 範囲: `player` 行のマヤと予算、そのプレイヤーの手札と衛星に付いたカード（位置・段・使用済み・メモ）、そのプレイヤーの衛星
 * （位置・向き・消費燃料・着陸猶予・使用済み・メモ）。山札・捨て札は含まない（山札から引いた結果は [Reset] 不可で、売却して
 * 捨て札へ移したカードは手札の行として復元する）。窓のプール（`pool`）は、ステージの解決が使い捨ての状態で行う（窓を開かない）ので
 * 保存しない。
 */
final class DeployStageSnapshot
{
    private function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function capture(Database $db, int $playerId): array
    {
        $player = $db->rows("SELECT `maya`, `budget` FROM `player` WHERE `player_id` = $playerId");
        $sats = $db->rows(
            'SELECT `sat_id`, `sat_cell`, `sat_dir`, `sat_fuel_used`, `sat_grace`, `sat_activation`, `sat_memo` '
            . "FROM `satellite` WHERE `sat_player` = $playerId ORDER BY `sat_id`",
        );
        $satIds = array_map(static fn(array $r): int => (int) $r['sat_id'], $sats);
        $where = "(`card_location` = 'hand' AND `card_location_arg` = $playerId)";
        if ($satIds !== []) {
            $where .= " OR (`card_location` = 'satellite' AND `card_location_arg` IN (" . Sql::intList($satIds) . '))';
        }
        $cards = $db->rows(
            'SELECT `card_id`, `card_location`, `card_location_arg`, `card_order`, `card_activation`, `card_memo` '
            . "FROM `project_card` WHERE $where ORDER BY `card_id`",
        );

        return [
            'maya' => (int) ($player[0]['maya'] ?? 0),
            'budget' => (int) ($player[0]['budget'] ?? 0),
            'sats' => $sats,
            'cards' => $cards,
        ];
    }

    /**
     * @param array<string, mixed> $snapshot `capture()` の結果
     */
    public static function restore(Database $db, int $playerId, array $snapshot): void
    {
        $db->execute('UPDATE `player` SET `maya` = ' . (int) $snapshot['maya'] . ', `budget` = ' . (int) $snapshot['budget'] . " WHERE `player_id` = $playerId");
        foreach ((array) $snapshot['sats'] as $row) {
            $db->execute(
                'UPDATE `satellite` SET `sat_cell` = ' . Sql::nullableInt($row['sat_cell'] === null ? null : (int) $row['sat_cell'])
                . ', `sat_dir` = ' . Sql::nullableStr($db, $row['sat_dir'] === null ? null : (string) $row['sat_dir'])
                . ', `sat_fuel_used` = ' . (int) $row['sat_fuel_used'] . ', `sat_grace` = ' . (int) $row['sat_grace']
                . ', `sat_activation` = ' . (int) $row['sat_activation']
                . ', `sat_memo` = ' . Sql::nullableStr($db, $row['sat_memo'] === null ? null : (string) $row['sat_memo'])
                . ' WHERE `sat_id` = ' . (int) $row['sat_id'],
            );
        }
        foreach ((array) $snapshot['cards'] as $row) {
            $db->execute(
                'UPDATE `project_card` SET `card_location` = ' . Sql::str($db, (string) $row['card_location'])
                . ', `card_location_arg` = ' . (int) $row['card_location_arg'] . ', `card_order` = ' . (int) $row['card_order']
                . ', `card_activation` = ' . (int) $row['card_activation']
                . ', `card_memo` = ' . Sql::nullableStr($db, $row['card_memo'] === null ? null : (string) $row['card_memo'])
                . ' WHERE `card_id` = ' . (int) $row['card_id'],
            );
        }
    }
}
