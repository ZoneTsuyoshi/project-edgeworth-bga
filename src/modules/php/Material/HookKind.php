<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * フックの種類（011 §3）。
 */
enum HookKind: string
{
    /** 窓フック: イベントが起きたときプールにトリガーを積み、状態 30〜32 で解決する */
    case Window = 'WINDOW';
    /** ステップ内フック: ドライバの GAME 状態の中で自動処理する（または 91 の問いに含める） */
    case Step = 'STEP';
    /** 問い合わせフック: 窓を開かず、数量を決める時点に修正部品を集めて畳み込む */
    case Query = 'QUERY';
}
