<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow;

use Bga\Games\projectedgeworth\Data\GlobalValues;
use Bga\Games\projectedgeworth\Data\LinkRepository;
use Bga\Games\projectedgeworth\Data\PlayerValues;
use Bga\Games\projectedgeworth\Data\SatelliteRepository;

/**
 * 終了判定（009 の 110 EndCheck）。循環フェイズが終わり着陸猶予も外した後の状態で行う（001 §10）。
 */
final class RoundEnd
{
    public function __construct(
        private readonly SatelliteRepository $satellites,
        private readonly LinkRepository $links,
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
    ) {
    }

    /**
     * @return list<PlayerProgress> 席順
     */
    public function playerProgress(): array
    {
        $active = [];
        foreach ($this->satellites->all() as $sat) {
            if ($sat->isActive()) {
                $active[$sat->playerId] = ($active[$sat->playerId] ?? 0) + 1;
            }
        }
        $result = [];
        foreach ($this->players->playerIds() as $id) {
            $result[] = new PlayerProgress($id, $active[$id] ?? 0, $this->links->countFor($id));
        }
        return $result;
    }

    /**
     * 終了判定。満たしたプレイヤーを end_condition_players に記録し（名声 IX）、進行度の最高値を更新する。
     *
     * @return bool ゲーム終了なら true（120 EndHandCashOut へ）。false なら次のラウンドへ
     */
    public function check(): bool
    {
        $progress = $this->playerProgress();
        $met = EndCondition::metBy($progress);
        $this->globals->setEndConditionPlayers($met);
        $this->globals->setProgressHighWater(EndCondition::progress($progress, $this->globals->progressHighWater()));
        return $met !== [];
    }

    /** getGameProgression の値（0〜99）。check() が保存した最高値 */
    public function gameProgression(): int
    {
        $progress = EndCondition::progress($this->playerProgress(), $this->globals->progressHighWater());
        $this->globals->setProgressHighWater($progress);
        return $progress;
    }
}
