<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Setup;

/**
 * ドラフトのパックの渡し先（013 §3.1）。席順の次の人（左隣）。最後の人の次は先頭。
 */
final class PackPassing
{
    private function __construct()
    {
    }

    /**
     * @param list<int> $order 席順のプレイヤー ID（基準プレイヤーから）
     */
    public static function receiver(array $order, int $holder): int
    {
        $index = array_search($holder, $order, true);
        if ($index === false) {
            throw new \InvalidArgumentException("player $holder is not seated");
        }
        return $order[($index + 1) % count($order)];
    }
}
