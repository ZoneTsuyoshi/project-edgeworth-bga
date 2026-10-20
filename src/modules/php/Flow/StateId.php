<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow;

/**
 * 状態 ID（仕様書 009 §3）。transitions の行き先と、グローバル値 window_resume に使う。
 * 1 と 99 は BGA の予約（初期状態・ゲーム終了）なので使わない。本番稼働後は値を変えない。
 * BGA は States/ の中の全クラスを状態として読むので、この定数クラスは States/ に置かない。
 * 15・16（ドラフト）は初版では実装しない（ID だけ予約。Q-06、009 未確定 8）。
 */
final class StateId
{
    // セットアップ（10〜17）
    public const SETUP_LAUNCH_PAD_NEXT = 10;
    public const SETUP_LAUNCH_PAD_PLACE = 11;
    public const SETUP_HAND_ROUTE = 12;
    public const SETUP_MULLIGAN = 13;
    public const SETUP_MULLIGAN_APPLY = 14;
    public const SETUP_FINISH = 17;

    // ラウンド・計画フェイズ（20〜23）
    public const ROUND_START = 20;
    public const PLANNING_COLLECT = 21;
    public const PLANNING_PLOT = 22;
    public const PLANNING_REVEAL = 23;

    // 効果の窓（30〜32）
    public const EFFECT_WINDOW_OPEN = 30;
    public const EFFECT_WINDOW_RESOLVE = 31;
    public const EFFECT_WINDOW_BARRIER = 32;

    // アクションフェイズ（40〜91）
    public const ACTION_PHASE_ROUTER = 40;
    public const BUDGET_DRIVER = 50;
    public const DEPLOY_DRIVER = 60;
    public const DEPLOY_MAIN_CHOOSE = 61; // 62 は欠番（配備のボーナスは通常の配備と同時に選ぶ。作者回答 2026-10-20）
    public const LAUNCH_DRIVER = 70;
    public const LAUNCH_MAIN_CHOOSE = 71;
    public const LAUNCH_BONUS_CHOOSE = 72;
    public const LINK_DRIVER = 80;
    public const LINK_MAIN_CHOOSE = 81;
    public const LINK_BONUS_CHOOSE = 82;
    public const PROPOSAL_DRIVER = 90;
    public const PROPOSAL_KEEP_CHOOSE = 91;

    // 循環・終了判定・ゲームの終了（100〜121）
    public const ORBIT_DRIVER = 100;
    public const END_CHECK = 110;
    public const END_HAND_CASH_OUT = 120;
    public const FINAL_SCORING = 121;

    private function __construct()
    {
    }
}
