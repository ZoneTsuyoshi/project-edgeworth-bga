<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow;

/**
 * 循環フェイズへのカード効果の差し込み口（011 のフック）。既定の NoOrbitModifiers は「修正なし」。
 * 効果エンジン（I-12 以降）が実装を差し替える。
 */
interface OrbitModifiers
{
    /** O1: 基本の 1 区域に加えて追加で進める区域数（P082 など）。追加移動ではニアミスは起きない */
    public function extraMoves(OrbitSatellite $sat): int;

    /**
     * O1（移動直後、O2 の前）: 燃料の増減。正が消費、負が回復（P083・P084 の回復など）。
     * 庫外の衛星には適用されない（FuelMath::change）。
     */
    public function entryFuelDelta(OrbitSatellite $sat): int;

    /** O2: 燃料消費の量。既定 1（P079・P080 は 0）。移動後の位置で問い合わせる（FAQ 行 33） */
    public function moveFuelCost(OrbitSatellite $sat): int;

    /** O3: この衛星はニアミスしない（P094。007 §5-6 の人数の扱いは OrbitResolver が行う） */
    public function nearMissExempt(OrbitSatellite $sat): bool;

    /** O3: ニアミスで消費する燃料。既定 2（P085 は 1） */
    public function nearMissFuelCost(OrbitSatellite $sat): int;
}
