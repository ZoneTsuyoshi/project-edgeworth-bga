<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Result;

use Bga\Games\projectedgeworth\Effects\Parts\PartCall;
use Bga\Games\projectedgeworth\Material\Hook;

/**
 * `ACTION_BONUS_STEP`（R012・R013）の効果が、どのアクションのボーナスに働くかの絞り込み。
 * 定義の `params['action']`（`ActionKind` の値）が、イベントの `data['action']` と一致するときだけ働く。
 * `ACTION_BONUS_STEP` 以外のフックの効果（`action` を持たない）は常に通す。
 */
final class ActionBonusFilter
{
    private function __construct()
    {
    }

    public static function allows(PartCall $call): bool
    {
        if ($call->spec->hook !== Hook::ActionBonusStep) {
            return true;
        }

        return ($call->spec->params['action'] ?? null) === ($call->item->event->data['action'] ?? null);
    }
}
