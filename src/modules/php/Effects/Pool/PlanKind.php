<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Pool;

enum PlanKind: string
{
    /** プールが空 */
    case Done = 'DONE';
    /** 順序が未決定の衝突する集合がある（`ORDER` の問い） */
    case Order = 'ORDER';
    /** 問いなしに自動で解決できる効果がある */
    case Auto = 'AUTO';
    /** [Use] / [Skip] や中身の選択を聞く効果がある（`EFFECT` の問い） */
    case Ask = 'ASK';
}
