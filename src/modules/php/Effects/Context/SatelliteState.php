<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Context;

use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Material\SatelliteShape;

/**
 * 衛星 1 機のその時点の状態（E-4・E-5 の部品が読む。001 §4-4）。
 *
 * - `cell`・`direction`: 稼働中の位置と向き（非稼働は null）。
 * - `fuelUsed`: 燃料の消費量 c。燃料庫の外（燃料切れ）は c = C + 1 で止まる。
 * - `capacity`: 燃料庫の数 C（衛星カード + 配備されているプロジェクトカード）。
 */
final readonly class SatelliteState
{
    public function __construct(
        public int $id,
        public int $playerId,
        public SatelliteShape $shape,
        public ?int $cell,
        public ?Direction $direction,
        public int $fuelUsed,
        public int $capacity,
    ) {
    }

    public function isActive(): bool
    {
        return $this->cell !== null;
    }

    /** 燃料切れ（c > C） */
    public function isOutOfFuel(): bool
    {
        return $this->fuelUsed > $this->capacity;
    }

    /** 残り燃料 R = C − c（庫外なら 0） */
    public function remainingFuel(): int
    {
        return max(0, $this->capacity - $this->fuelUsed);
    }

    /** 燃料を消費して燃料庫の外に出るところまで進められる区域数 C + 1 − c（庫外なら 0。FAQ 行 15） */
    public function stepsUntilOut(): int
    {
        return max(0, $this->capacity + 1 - $this->fuelUsed);
    }
}
