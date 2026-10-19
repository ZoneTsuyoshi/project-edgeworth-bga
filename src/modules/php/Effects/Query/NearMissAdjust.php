<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Query;

/**
 * ニアミスの問い合わせ `NEAR_MISS` の値（011 §3-2）: この衛星を対象から除くか（M13）と、消費する燃料（M12）。
 */
final readonly class NearMissAdjust
{
    public function __construct(
        public bool $exempt = false,
        public int $fuel = 2,
    ) {
    }

    public function withExempt(): self
    {
        return new self(true, $this->fuel);
    }

    public function withFuel(int $fuel): self
    {
        return new self($this->exempt, $fuel);
    }
}
