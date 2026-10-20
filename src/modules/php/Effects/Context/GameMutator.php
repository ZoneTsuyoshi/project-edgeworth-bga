<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Context;

use Bga\Games\ProjectEdgeworth\Board\Direction;

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

    // ---- E-4 で足した書き込み（R06〜R08・R13） ----
    //
    // 配備・除去・付け替えで燃料庫の数 C が変わっても、実装は消費量 c を調整しない。呼び出し側の部品が
    // `Effects\Parts\Result\FuelAdjust` で `setFuelUsed()` を呼び、燃料切れの衛星を燃料切れのままにする（001 U-6）。

    /**
     * 燃料を消費する（R06・R07）。庫外（燃料切れ）の衛星は動かさず、庫外に出たら c = C + 1 で止まる。
     *
     * @return int 実際に増えた消費量
     */
    public function spendFuel(int $satelliteId, int $amount): int;

    /** 消費量 c をそのまま書く（燃料庫の数が変わったとき、入れ替えの後の調整。`FuelMath::afterCapacityChange()` の結果を渡す） */
    public function setFuelUsed(int $satelliteId, int $used): void;

    /** 稼働中の衛星の位置と向きを書く（R07。移動の結果。反射で向きが変わることがある） */
    public function moveSatellite(int $satelliteId, int $cell, Direction $direction): void;

    /** 稼働中の衛星の向きを変える（R08） */
    public function turnSatellite(int $satelliteId, Direction $direction): void;

    /** 2 機の稼働状態（位置・向き）と消費量 c をそのまま入れ替える（R13。打ち上げ・着陸は起こさない。庫外の調整は呼び出し側） */
    public function swapSatellites(int $satelliteIdA, int $satelliteIdB): void;

    // ---- E-5 で足した書き込み（R09・R10・R12） ----

    /** 手札のカードを衛星の一番上に配備する（R09。コストの支払いは呼び出し側。上限 4 枚の確認も呼び出し側） */
    public function deployFromHand(int $playerId, int $cardNo, int $satelliteId): void;

    /**
     * 山札の一番上を衛星に配備する（R10）。山札が空なら捨て札をシャッフルして山札にする。両方空なら配備せず null。
     *
     * @return int|null 配備したカード番号
     */
    public function deployFromDeck(int $playerId, int $satelliteId): ?int;

    /** 配備されているカードを捨て札にする（R10。上のカードが下がる） */
    public function discardDeployed(int $cardNo): void;

    /**
     * 非稼働の衛星のカードの付け替え後の配置を書く（R12）。引数に含まれる衛星だけが対象で、それぞれの衛星のカードの下からの順を渡す。
     *
     * @param array<int, list<int>> $placement 衛星 ID => 配備するカード番号（下の段から）
     */
    public function rearrangeDeployed(array $placement): void;
}
