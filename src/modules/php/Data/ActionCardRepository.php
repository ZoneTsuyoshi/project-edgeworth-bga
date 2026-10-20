<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

/**
 * action_card テーブル（010 §6.1）。常に公開情報。プロット中のカードは ChoiceRepository が持ち、公開（23）までここは変えない。
 */
final class ActionCardRepository
{
    /** I 予算発行（A001）〜 V 企画（A005） */
    public const ACTION_NUMBERS = [1, 2, 3, 4, 5];
    /** 回収しても手札に戻る（横にならない）I 予算発行 */
    public const BUDGET_ACTION = 1;

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * プレイヤーの 5 枚を手札として作る（setupNewGame 用）。
     */
    public function createFor(int $playerId): void
    {
        $values = array_map(static fn(int $no): string => "($playerId, $no, 0)", self::ACTION_NUMBERS);
        $this->db->execute('INSERT INTO `action_card` (`action_player`, `action_no`, `action_state`) VALUES ' . implode(', ', $values));
    }

    public function state(int $playerId, int $actionNo): ActionCardState
    {
        $value = $this->db->value(
            "SELECT `action_state` FROM `action_card` WHERE `action_player` = $playerId AND `action_no` = $actionNo",
        );
        if ($value === null) {
            throw new \InvalidArgumentException("action card not found: player $playerId, action $actionNo");
        }
        return ActionCardState::from((int) $value);
    }

    /**
     * 手札にあるアクションの番号（昇順）。
     *
     * @return list<int>
     */
    public function handOf(int $playerId): array
    {
        return $this->numbersIn($playerId, ActionCardState::Hand);
    }

    /**
     * 全員分の状態。
     *
     * @return array<int, array<int, ActionCardState>> プレイヤー ID => (アクション番号 => 状態)
     */
    public function all(): array
    {
        $map = [];
        $rows = $this->db->rows(
            'SELECT `action_player`, `action_no`, `action_state` FROM `action_card` ORDER BY `action_player`, `action_no`',
        );
        foreach ($rows as $r) {
            $map[(int) $r['action_player']][(int) $r['action_no']] = ActionCardState::from((int) $r['action_state']);
        }
        return $map;
    }

    /**
     * 公開（23）。手札のカードを縦にする。縦のカードが既にあるプレイヤーは例外（縦は 1 人 1 枚）。
     */
    public function reveal(int $playerId, int $actionNo): void
    {
        if ($this->state($playerId, $actionNo) !== ActionCardState::Hand) {
            throw new \DomainException("action $actionNo is not in the hand of player $playerId");
        }
        if ($this->numbersIn($playerId, ActionCardState::Upright) !== []) {
            throw new \DomainException("player $playerId already has an upright action card");
        }
        $this->setState($playerId, $actionNo, ActionCardState::Upright);
    }

    /**
     * 計画フェイズの回収（21。001 §6-1）。この順に: 横 → 手札、縦の I → 手札、残りの縦 → 横。
     */
    public function retrieve(): void
    {
        $sideways = ActionCardState::Sideways->value;
        $upright = ActionCardState::Upright->value;
        $hand = ActionCardState::Hand->value;
        $this->db->execute("UPDATE `action_card` SET `action_state` = $hand WHERE `action_state` = $sideways");
        $this->db->execute(
            "UPDATE `action_card` SET `action_state` = $hand WHERE `action_state` = $upright AND `action_no` = "
            . self::BUDGET_ACTION,
        );
        $this->db->execute("UPDATE `action_card` SET `action_state` = $sideways WHERE `action_state` = $upright");
    }

    private function setState(int $playerId, int $actionNo, ActionCardState $state): void
    {
        $this->db->execute(
            "UPDATE `action_card` SET `action_state` = {$state->value} "
            . "WHERE `action_player` = $playerId AND `action_no` = $actionNo",
        );
    }

    /**
     * @return list<int>
     */
    private function numbersIn(int $playerId, ActionCardState $state): array
    {
        $rows = $this->db->rows(
            "SELECT `action_no` FROM `action_card` WHERE `action_player` = $playerId AND `action_state` = {$state->value} "
            . 'ORDER BY `action_no`',
        );
        return array_map(static fn(array $r): int => (int) $r['action_no'], $rows);
    }
}
