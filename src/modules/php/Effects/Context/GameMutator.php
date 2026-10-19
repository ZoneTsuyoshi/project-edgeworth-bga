<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Context;

/**
 * 効果エンジンがゲーム状態を書くための口。エンジン自身が書くのは起動効果の使用済みと [Use] の支払いだけで、
 * 効果の結果（引く、燃料、向きなど）は部品が書く。E-1 以降で部品が必要とするメソッドをここに足す。
 */
interface GameMutator
{
    /** 起動効果を使用済みにする（効果が実際に解決されたとき。[Skip]・対象なしでは呼ばない。007 §3-1） */
    public function markUsed(EffectHost $host): void;

    public function payMaya(int $playerId, int $amount): void;

    /**
     * 手札を売却してマヤを得る（008 §2-6）。
     *
     * @param list<int> $cardIds
     */
    public function sellCards(int $playerId, array $cardIds): void;
}
