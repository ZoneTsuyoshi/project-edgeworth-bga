<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow;

/**
 * 同時選択で、確定した後にアクティブ状態をどうするか（009 §5.3、004 未確定事項 No.2）。案 A / 案 B の違いはここだけに閉じ込める。
 *
 * - 案 B: 確定した人を非アクティブにする。変更アクションは #[CheckAction(false)] で受ける。最後の 1 人の確定でフレームワークが遷移する。
 * - 案 A: 全員確定までアクティブのまま。DB の確定一覧で全員確定を判定して遷移する。
 *
 * 最初の実装は案 B（009 V-1）。Studio で非アクティブなプレイヤーの確定の変更が受け付けられない場合は、CURRENT を MODE_A にする。
 */
final class ConfirmMode
{
    public const MODE_A = 'A';
    public const MODE_B = 'B';
    /** 切り替えの定数（ここだけを変える） */
    public const CURRENT = self::MODE_B;

    private function __construct()
    {
    }

    /**
     * @param bool $isFirstConfirm このプレイヤーの、この状態での最初の確定か（選び直しではないか）
     * @param bool $allConfirmedInDb 今の確定で DB の確定一覧がそろったか
     */
    public static function plan(bool $isFirstConfirm, bool $allConfirmedInDb, string $mode = self::CURRENT): ConfirmAction
    {
        if ($mode === self::MODE_A) {
            return $allConfirmedInDb ? ConfirmAction::DeactivateAll : ConfirmAction::None;
        }
        return $isFirstConfirm ? ConfirmAction::DeactivatePlayer : ConfirmAction::None;
    }
}
