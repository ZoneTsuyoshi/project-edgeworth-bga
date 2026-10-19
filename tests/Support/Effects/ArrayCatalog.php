<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support\Effects;

use Bga\Games\ProjectEdgeworth\Material\EffectCatalog;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;

/**
 * 配列の `EffectCatalog`（テスト用の疑似カードの定義を差し込む）。
 */
final class ArrayCatalog implements EffectCatalog
{
    /** @var array<string, EffectSpec> */
    private array $specs = [];

    /**
     * @param list<EffectSpec> $specs
     */
    public function __construct(array $specs = [])
    {
        foreach ($specs as $spec) {
            $this->specs[$spec->cardKey] = $spec;
        }
    }

    public function specFor(string $cardKey): ?EffectSpec
    {
        return $this->specs[$cardKey] ?? null;
    }
}
