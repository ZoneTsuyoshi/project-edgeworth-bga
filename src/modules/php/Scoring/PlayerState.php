<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Scoring;

/**
 * 名声計算・褒賞の比較値の入力となる、1 人のプレイヤーの状態。
 */
final class PlayerState
{
    /**
     * @param int $handCount プロジェクトカードの手札の枚数（ゲーム終了時は換金の対象）
     * @param list<LinkedCube> $cubes 通信確立済みの区域のキューブ
     * @param int|null $launchPadCell 発射拠点の区域 ID（通信確立済みの区域として扱う。001 §5、Q-18）
     * @param list<SatelliteState> $satellites
     * @param list<int> $missionIds ミッションカードの番号（1〜10）
     * @param bool $endConditionMet ゲーム終了条件を満たしたか（名声 IX）
     */
    public function __construct(
        public readonly int $playerId,
        public readonly int $budget,
        public readonly int $maya,
        public readonly int $handCount,
        public readonly array $cubes,
        public readonly ?int $launchPadCell,
        public readonly array $satellites,
        public readonly array $missionIds,
        public readonly bool $endConditionMet = false,
    ) {
    }

    /**
     * 通信確立済み区域の集合 P（キューブの区域 + 発射拠点の区域）。昇順・重複なし。
     *
     * @return list<int>
     */
    public function linkedCells(): array
    {
        $set = [];
        foreach ($this->cubes as $cube) {
            $set[$cube->cellId] = true;
        }
        if ($this->launchPadCell !== null) {
            $set[$this->launchPadCell] = true;
        }
        $cells = array_keys($set);
        sort($cells);
        return $cells;
    }

    /** 手札を売却して換金した後の状態（手札 0、マヤ加算。001 §10・Q-12） */
    public function withHandCashedOut(): self
    {
        return new self(
            $this->playerId,
            $this->budget,
            $this->maya + HandCashOut::mayaFor($this),
            0,
            $this->cubes,
            $this->launchPadCell,
            $this->satellites,
            $this->missionIds,
            $this->endConditionMet,
        );
    }
}
