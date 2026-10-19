<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Pool;

/**
 * プールの要素の状態（011 §9）。
 */
enum PoolItemStatus: string
{
    /** 未解決（順序の選択待ち・[Use] / [Skip] 待ち・自動で解決待ちを含む） */
    case Pending = 'PENDING';
    /** 解決済み */
    case Done = 'DONE';
    /** [Skip] した（使用済みにならず、そのトリガーは失効する。007 §3-1） */
    case Skipped = 'SKIPPED';
    /** 再確認（稼働条件・条件・対象の有無）に失敗した、または窓の強制終了で捨てた */
    case Dropped = 'DROPPED';
}
