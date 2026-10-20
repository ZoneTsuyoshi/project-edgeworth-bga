<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 資源の範囲（011 §6-4）。
 */
enum ResourceScope: string
{
    /** プレイヤー単位の資源（マヤ、予算、手札） */
    case Player = 'PLAYER';
    /** 効果を持つカードのある衛星（`SAT(この衛星)`） */
    case HostSatellite = 'HOST_SAT';
    /** イベントの衛星（`SAT(イベントの衛星)`。P038 の減らす先など） */
    case EventSatellite = 'EVENT_SAT';
    /** 特定の衛星（`SAT(id)`。プールに積むとき `ResourceTouch::bind()` が `HostSatellite` / `EventSatellite` から作る） */
    case Satellite = 'SAT';
    /** 自分の全ての衛星（衛星や対象をプレイヤーが選ぶ効果。007 §6-2 の規則 4） */
    case AllSatellites = 'ALL_SATS';
}
