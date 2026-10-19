<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

use Bga\Games\ProjectEdgeworth\Data\ActionCardRepository;

/**
 * I 予算発行（001 §7-2、008 §3-9）。選択はない。メインは全員が予算の値だけマヤを得る（B1）。
 * ボーナス（I を選んだプレイヤー）は追加で 6 マヤ（B2）。予算が上がる効果の結果は予算の値に反映済みとして読む。
 */
final class BudgetPhase
{
    public const BONUS_GAIN = 6;

    public function __construct(private readonly ActionServices $services)
    {
    }

    /** メインで得るマヤ（フックによる加算を含む。下限 0） */
    public function mainGain(int $playerId): int
    {
        $budget = $this->services->players->budget($playerId);
        return max(0, $this->services->hooks->budgetMainGain($playerId, $budget));
    }

    /**
     * メイン: 全員にマヤを与える。
     *
     * @return array<int, int> プレイヤー ID => 得たマヤ（席順）
     */
    public function applyMain(): array
    {
        $gains = [];
        foreach ($this->services->players->playerIds() as $playerId) {
            $gain = $this->mainGain($playerId);
            $this->services->players->addMaya($playerId, $gain);
            $gains[$playerId] = $gain;
        }
        return $gains;
    }

    /**
     * ボーナス: 保持者に 6 マヤを与える。
     *
     * @param list<int>|null $holders null なら bonus_holders の I の保持者
     * @return array<int, int> プレイヤー ID => 得たマヤ（席順）
     */
    public function applyBonus(?array $holders = null): array
    {
        $holders = $this->services->seatOrder($holders ?? $this->services->bonusHolders(ActionCardRepository::BUDGET_ACTION));
        $gains = [];
        foreach ($holders as $playerId) {
            $this->services->players->addMaya($playerId, self::BONUS_GAIN);
            $gains[$playerId] = self::BONUS_GAIN;
        }
        return $gains;
    }
}
