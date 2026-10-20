<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Scoring;

use Bga\Games\projectedgeworth\Material\ProjectCards;
use Bga\Games\projectedgeworth\Material\ProjectType;
use Bga\Games\projectedgeworth\Material\SatelliteCards;

/**
 * 衛星 1 機の状態（名声計算・褒賞の比較値の入力）。
 */
final class SatelliteState
{
    /**
     * @param string $cardId 衛星カードの ID（R001B、R006 など）
     * @param bool $active 稼働中か
     * @param int $fuelUsed 消費した燃料 c（燃料切れは C + 1。001 §4-4）。非稼働は 0
     * @param list<string> $deployedCardIds 配備されているプロジェクトカードの ID
     */
    public function __construct(
        public readonly int $satId,
        public readonly string $cardId,
        public readonly bool $active,
        public readonly int $fuelUsed,
        public readonly array $deployedCardIds,
    ) {
    }

    /** 衛星カードに記載の名声（稼働中のみ名声 VI に入る） */
    public function satelliteFame(): int
    {
        return SatelliteCards::get($this->cardId)->fame;
    }

    /** 配備されているプロジェクトカードの名声の合計 */
    public function deployedFame(): int
    {
        $sum = 0;
        foreach ($this->deployedCardIds as $id) {
            $sum += ProjectCards::get($id)->fame;
        }
        return $sum;
    }

    /** 燃料庫の数 C = 衛星カード + 配備カード（001 §4-4） */
    public function fuelCapacity(): int
    {
        $sum = SatelliteCards::get($this->cardId)->fuelSlots;
        foreach ($this->deployedCardIds as $id) {
            $sum += ProjectCards::get($id)->fuelSlots;
        }
        return $sum;
    }

    /** 残燃料 R = C - c。燃料コマが庫外（c > C）なら 0（FAQ 行 85） */
    public function remainingFuel(): int
    {
        return max(0, $this->fuelCapacity() - $this->fuelUsed);
    }

    public function deployedCount(ProjectType $type): int
    {
        $n = 0;
        foreach ($this->deployedCardIds as $id) {
            if (ProjectCards::get($id)->type === $type) {
                $n++;
            }
        }
        return $n;
    }

    /** CREW・DEVICE・SOFTWARE がそろっているか（稼働・非稼働を問わない） */
    public function hasAllTypes(): bool
    {
        foreach (ProjectType::cases() as $type) {
            if ($this->deployedCount($type) === 0) {
                return false;
            }
        }
        return true;
    }

    public function isDeployed(string $projectCardId): bool
    {
        return in_array($projectCardId, $this->deployedCardIds, true);
    }
}
