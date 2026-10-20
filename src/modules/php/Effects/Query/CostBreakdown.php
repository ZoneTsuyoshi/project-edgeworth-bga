<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Query;

/**
 * コストの内訳（007 §5-2〜§5-4）。修正は加算で重なり、下限 0 は全修正の後に 1 回だけ適用する。
 * 特殊通信確立の +5 のように下限の後に足す分は `afterFloor` に入れる（007 §5-4 の手順 6）。
 */
final readonly class CostBreakdown
{
    /**
     * @param list<array{source: string, delta: int}> $modifiers 修正（delta は引くとき負）
     * @param list<array{source: string, delta: int}> $afterFloor 下限 0 の後に足す分
     */
    public function __construct(
        public int $base = 0,
        public array $modifiers = [],
        public array $afterFloor = [],
    ) {
    }

    public function withModifier(string $source, int $delta): self
    {
        return new self($this->base, [...$this->modifiers, ['source' => $source, 'delta' => $delta]], $this->afterFloor);
    }

    public function withAfterFloor(string $source, int $delta): self
    {
        return new self($this->base, $this->modifiers, [...$this->afterFloor, ['source' => $source, 'delta' => $delta]]);
    }

    /** 修正の合計（引いた量は負） */
    public function modifierSum(): int
    {
        return array_sum(array_column($this->modifiers, 'delta'));
    }

    /** 引く量（正）。`ActionHooks` の「修正で引く量」 */
    public function reduction(): int
    {
        return -$this->modifierSum();
    }

    public function total(): int
    {
        return max(0, $this->base + $this->modifierSum()) + array_sum(array_column($this->afterFloor, 'delta'));
    }
}
