<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * 通信確立で得た区域ボーナス（001 §8）。
 */
final class AreaBonus
{
    /**
     * @param int $maya 得たマヤ（森林 +5、雪原 +1）
     * @param list<int> $drawn 引いたプロジェクトカード（山岳）
     * @param int $fuelRecovered 実際に回復した燃料（深海。燃料切れの衛星・消費 0 の衛星は 0）
     */
    public function __construct(
        public readonly int $maya = 0,
        public readonly array $drawn = [],
        public readonly int $fuelRecovered = 0,
    ) {
    }
}
