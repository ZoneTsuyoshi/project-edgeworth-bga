<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow;

/**
 * 確定の直後にフレームワークへ何をするか（ConfirmMode::plan の結果）。
 */
enum ConfirmAction
{
    /** 何もしない */
    case None;
    /** このプレイヤーだけを非アクティブにする（setPlayerNonMultiactive。最後の 1 人ならフレームワークが遷移する） */
    case DeactivatePlayer;
    /** 全員を非アクティブにして遷移する（setAllPlayersNonMultiactive） */
    case DeactivateAll;
}
