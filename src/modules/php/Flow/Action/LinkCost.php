<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * 通信確立コストの内訳（001 §7-5、007 §5-4）。
 *
 * 区域コスト（陸 5・海 2、岩礁の割引後）+ 先駆コスト（既にキューブ・発射拠点のある他プレイヤー 1 人につき 3）から
 * 効果による修正を引いて下限 0（区域コストから先に引き、足りなければ先駆コストから。007 未確定 7 の暫定）。
 * 特殊通信確立は、下限 0 を適用した後に特殊コスト 5 を足す（FAQ 行 74）。
 */
final class LinkCost
{
    public const PIONEER_UNIT = 3;
    public const SPECIAL_COST = 5;

    /**
     * @param int $baseArea 区域の種類による区域コスト（岩礁の割引前）
     * @param int $reefDiscount 岩礁による区域コストの割引（0 または 1）
     * @param int $reduction 効果による修正で引く量
     * @param int $areaCost 修正後に実際に払う区域コスト（サプライへ）
     * @param list<array{to: int, amount: int}> $pioneer 修正後に実際に払う先駆コスト（受け取り人ごと。席順）
     * @param int $specialCost 特殊通信確立の追加コスト（通常は 0）
     */
    public function __construct(
        public readonly int $baseArea,
        public readonly int $reefDiscount,
        public readonly int $reduction,
        public readonly int $areaCost,
        public readonly array $pioneer,
        public readonly int $specialCost,
    ) {
    }

    public function pioneerTotal(): int
    {
        return array_sum(array_column($this->pioneer, 'amount'));
    }

    public function total(): int
    {
        return $this->areaCost + $this->pioneerTotal() + $this->specialCost;
    }
}
