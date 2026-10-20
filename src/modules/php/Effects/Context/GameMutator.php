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

    // ---- E-2 で足した書き込み（結果部品 R01〜R03・R05・R15） ----

    /** マヤを得る（R01） */
    public function gainMaya(int $playerId, int $amount): void;

    /** 予算を上げる。上限なし（R02。001 U-5） */
    public function changeBudget(int $playerId, int $amount): void;

    /**
     * 山札から手札に引く。山札が空になったら捨て札をシャッフルして山札にする（FAQ 行 3）。両方空なら引けた分だけ。
     *
     * @return int 実際に引いた枚数
     */
    public function drawCards(int $playerId, int $count): int;

    /**
     * 燃料を回復する（R05。001 §4-4）。消費量 c を減らし、初期燃料庫より下には動かない。燃料庫の外にあるコマは動かさない（FAQ 行 14）。
     *
     * @return int 実際に回復した量
     */
    public function recoverFuel(int $satelliteId, int $amount): int;

    /** 計画フェイズの回収で、縦向きのアクションカード（I〜V = 1〜5）を横向きにせず手札に戻す（R15。P001・P002） */
    public function returnPlottedActionToHand(int $playerId, int $actionNo): void;

    // ---- E-3 で足した書き込み（R04・R14 と企画のオプション） ----

    /**
     * 手札から捨て札へ捨てる。売却ではないのでマヤは得ない（FAQ 行 31・60・70。R04、P068・P070・P087）。
     *
     * @param list<int> $cardIds
     */
    public function discardFromHand(int $playerId, array $cardIds): void;

    /** EUROPA の宣言で、そのアクション（`ActionKind` の値）のアクション選択ボーナスを得る者に加える（R14。011 §2-2、007 未確定 6） */
    public function declareBonus(int $playerId, string $action): void;
}
