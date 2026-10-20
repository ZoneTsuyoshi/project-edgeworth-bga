<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

/**
 * 効果の窓（007 の PL1〜O4）を開くかどうかの問い合わせ（009 §6.1）。ドライバが窓の位置で呼ぶ。
 *
 * 効果エンジンの本番結線（申し送り N-4・N-7）の差し込み口。結線後は、窓のイベントからトリガーされた効果が
 * 1 件でもあれば true を返す実装に差し替える（その実装が EffectEngine::openWindow に渡すイベントを窓の状態 30 に引き渡す）。
 */
interface WindowGate
{
    /**
     * @param string $windowId 007 の窓の記号（PL1・PL2・B0・XD・B2・B3・DE・LE・TE・KE・PA・PB・O0・O1・O4）
     */
    public function needsWindow(string $windowId): bool;
}
