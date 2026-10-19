<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Setup;

use Bga\Games\ProjectEdgeworth\Board\BoardFace;
use Bga\Games\ProjectEdgeworth\Board\BoardPlacement;
use Bga\Games\ProjectEdgeworth\Material\OptionId;

/**
 * セットアップに関わるゲームオプションの解釈結果（004 §7、001 §3）。
 */
final readonly class GameOptions
{
    /** ゲームオプション 101 の値 => 1 段のボード枚数 B */
    private const BOARD_COUNT_BY_SIZE = [1 => 4, 2 => 3, 3 => 2];

    public function __construct(
        public BoardFace $boardFace = BoardFace::STANDARD,
        public BoardPlacement $boardPlacement = BoardPlacement::EARTH,
        /** 1 段のボード枚数 B（4 = 2x4、3 = 2x3、2 = 2x2） */
        public int $boardCount = 4,
        public RewardSelection $rewardSelection = RewardSelection::InitialGame,
        public SatellitePlanOption $satellitePlan = SatellitePlanOption::Moon,
        public StartingHand $startingHand = StartingHand::None,
    ) {
        if (!in_array($boardCount, self::BOARD_COUNT_BY_SIZE, true)) {
            throw new \InvalidArgumentException("boardCount must be 2..4: {$boardCount}");
        }
    }

    /**
     * ゲームオプションの値（未設定は null で既定）から作る。人数と合わない盤面の大きさ、未実装の値は例外（004 §7）。
     *
     * @param callable(int): ?int $get オプション ID => 値（BGA では $this->tableOptions->get を渡す）
     */
    public static function fromTableOptions(int $playerCount, callable $get): self
    {
        if ($playerCount < 2 || $playerCount > 4) {
            throw new \InvalidArgumentException("unsupported number of players: {$playerCount}");
        }
        [$face, $placement] = BoardPlacement::fromOptionValue($get(OptionId::BOARD_LAYOUT) ?? 1);

        $size = $get(OptionId::BOARD_SIZE) ?? 1;
        $boardCount = self::BOARD_COUNT_BY_SIZE[$size] ?? throw new \InvalidArgumentException("Unknown board size option: {$size}");
        // 001 §3-2: 2 人は 2x2・2x3・2x4、3 人は 2x3・2x4、4 人は 2x4 のみ
        $minBoardCount = match ($playerCount) {
            2 => 2,
            3 => 3,
            default => 4,
        };
        if ($boardCount < $minBoardCount) {
            throw new \InvalidArgumentException("Board size option {$size} is not available for {$playerCount} players");
        }

        $award = $get(OptionId::AWARD_CARDS) ?? 1;
        $plan = $get(OptionId::SATELLITE_PLAN) ?? 1;
        $hand = $get(OptionId::STARTING_HAND) ?? 1;
        return new self(
            $face,
            $placement,
            $boardCount,
            RewardSelection::tryFrom($award) ?? throw new \InvalidArgumentException("Unknown award cards option: {$award}"),
            SatellitePlanOption::tryFrom($plan) ?? throw new \InvalidArgumentException("Unknown satellite plan option: {$plan}"),
            StartingHand::tryFrom($hand) ?? throw new \InvalidArgumentException("Unsupported starting hand option: {$hand}"),
        );
    }
}
