<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

/**
 * あるコストを、所持マヤ（と手札の売却）で払えるか（008 §2-6）。args の「売却すれば払える」の表示に使う。
 */
final class Payability
{
    /**
     * @param int $cost 払う額
     * @param int $maya 処理前の所持マヤ
     * @param int $price 手札 1 枚の売却額
     * @param int $sellableCards 売れる手札の枚数（選択に同梱するカードなど、売れないものを除く）
     */
    public function __construct(
        public readonly int $cost,
        public readonly int $maya,
        public readonly int $price,
        public readonly int $sellableCards,
    ) {
    }

    /** 売らずに払えるか */
    public function canPayNow(): bool
    {
        return $this->maya >= $this->cost;
    }

    public function shortfall(): int
    {
        return max(0, $this->cost - $this->maya);
    }

    /** 払うために最低限売る枚数。売っても届かないときは null */
    public function minSales(): ?int
    {
        $short = $this->shortfall();
        if ($short === 0) {
            return 0;
        }
        if ($this->price <= 0) {
            return null;
        }
        $needed = intdiv($short + $this->price - 1, $this->price);
        return $needed <= $this->sellableCards ? $needed : null;
    }

    /** 売却を使えば払えるか（売らずに払える場合も true） */
    public function canPayWithSales(): bool
    {
        return $this->minSales() !== null;
    }
}
