<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

use Bga\Games\projectedgeworth\Board\Adjacency;
use Bga\Games\projectedgeworth\Board\AreaType;
use Bga\Games\projectedgeworth\Board\BoardLayout;

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

    /**
     * 内訳を組み立てる（メイン・ボーナスの `LinkPhase` と、効果による通信確立のステージ（R11）で共通）。
     * 効果の修正 `$reduction` は区域コスト（岩礁の割引後）から先に引き、余りを先駆コストから席順に引く（007 未確定 7）。
     *
     * @param list<int> $recipients 先駆コストの受け取り人（席順。1 人につき 3）
     */
    public static function compute(AreaType $type, int $reefDiscount, int $reduction, array $recipients, bool $pioneerWaived, bool $special): self
    {
        $baseArea = $type->areaCost();
        $afterReef = max(0, $baseArea - $reefDiscount);

        $shares = [];
        if (!$pioneerWaived) {
            foreach ($recipients as $to) {
                $shares[] = ['to' => $to, 'amount' => self::PIONEER_UNIT];
            }
        }

        $reduction = max(0, $reduction);
        $area = max(0, $afterReef - $reduction);
        $remaining = max(0, $reduction - $afterReef);
        foreach ($shares as $i => $share) {
            $cut = min($remaining, $share['amount']);
            $shares[$i]['amount'] = $share['amount'] - $cut;
            $remaining -= $cut;
        }
        $shares = array_values(array_filter($shares, static fn(array $s): bool => $s['amount'] > 0));

        return new self($baseArea, $reefDiscount, $reduction, $area, $shares, $special ? self::SPECIAL_COST : 0);
    }

    /**
     * 岩礁の割引（001 §8、U-13）: 区域が陸で、本人が通信確立済みの岩礁に隣接していれば 1。
     *
     * @param list<int> $ownCells 本人のキューブがある区域
     */
    public static function reefDiscount(BoardLayout $layout, array $ownCells, int $cell): int
    {
        if (!$layout->areaType($cell)->isLand()) {
            return 0;
        }
        $geometry = $layout->geometry();
        $here = $geometry->cellFromId($cell);
        foreach (Adjacency::neighbors($geometry, $here->x, $here->y) as $near) {
            $id = $geometry->cellId($near->x, $near->y);
            if (in_array($id, $ownCells, true) && $layout->areaType($id) === AreaType::REEF) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * ステージに積む（`StagedLinkRequest::$cost`）ための配列。JSON にできる。
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'base' => $this->baseArea,
            'reef' => $this->reefDiscount,
            'reduction' => $this->reduction,
            'area' => $this->areaCost,
            'pioneer' => $this->pioneer,
            'special' => $this->specialCost,
        ];
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function fromArray(array $a): self
    {
        $pioneer = [];
        foreach ((array) ($a['pioneer'] ?? []) as $share) {
            $pioneer[] = ['to' => (int) $share['to'], 'amount' => (int) $share['amount']];
        }

        return new self(
            (int) ($a['base'] ?? 0),
            (int) ($a['reef'] ?? 0),
            (int) ($a['reduction'] ?? 0),
            (int) ($a['area'] ?? 0),
            $pioneer,
            (int) ($a['special'] ?? 0),
        );
    }
}
