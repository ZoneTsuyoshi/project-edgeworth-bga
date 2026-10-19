<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

use Bga\Games\ProjectEdgeworth\Data\Satellite;

/**
 * ある区域を通信確立できる自分の衛星 1 機と、その場合のコスト（008 §3-4）。
 */
final class LinkSatelliteOption
{
    public function __construct(
        public readonly Satellite $satellite,
        public readonly LinkCost $cost,
        public readonly Payability $payability,
    ) {
    }
}
