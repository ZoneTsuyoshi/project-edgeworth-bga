<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Pool;

use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Material\SatelliteShape;

/**
 * デフォルトの順（007 §6-3、Q-15）: 衛星は衛星カードの形状順（HAMMER、RETRO、SCALLOP、CONCORDE、STEALTH）、
 * 1 つの衛星の中では衛星カード → 一番早く配備したカードから上へ、衛星に属さない効果は最後。
 * [Confirm order] の初期の並びと、zombie の答えはこの順。
 */
final class DefaultOrder
{
    /**
     * @param list<PoolItem> $items
     * @param array<int, EffectHost> $hosts 要素 ID => 宿主
     * @return list<PoolItem>
     */
    public static function sort(array $items, array $hosts): array
    {
        usort($items, static function (PoolItem $a, PoolItem $b) use ($hosts): int {
            return self::key($hosts[$a->id] ?? null, $a->id) <=> self::key($hosts[$b->id] ?? null, $b->id);
        });

        return $items;
    }

    /**
     * @return array{int, int, int}
     */
    private static function key(?EffectHost $host, int $itemId): array
    {
        if ($host === null || $host->shape === null) {
            return [PHP_INT_MAX, 0, $itemId];
        }

        return [(int) array_search($host->shape, SatelliteShape::cases(), true), $host->deployOrder, $itemId];
    }
}
