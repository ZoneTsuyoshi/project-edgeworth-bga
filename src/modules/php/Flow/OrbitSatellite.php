<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow;

use Bga\Games\projectedgeworth\Board\Direction;

/**
 * 循環フェイズで扱う稼働中の衛星 1 機（不変）。DB に依存しない。
 * fuelUsed は 001 §4-4 の c（燃料切れは C + 1）、capacity は燃料庫の数 C。
 */
final class OrbitSatellite
{
    public function __construct(
        public readonly int $satId,
        public readonly int $playerId,
        public readonly int $cell,
        public readonly Direction $dir,
        public readonly int $fuelUsed,
        public readonly int $capacity,
    ) {
    }

    public function withPosition(int $cell, Direction $dir): self
    {
        return new self($this->satId, $this->playerId, $cell, $dir, $this->fuelUsed, $this->capacity);
    }

    public function withFuelUsed(int $fuelUsed): self
    {
        return new self($this->satId, $this->playerId, $this->cell, $this->dir, $fuelUsed, $this->capacity);
    }

    /** 燃料コマが燃料庫の外にある（c > C）。着陸 / 着水判定の対象 */
    public function isOutOfFuel(): bool
    {
        return FuelMath::isOutOfFuel($this->fuelUsed, $this->capacity);
    }
}
