<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts\Modifier;

use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Parts\ModifierPart;
use Bga\Games\ProjectEdgeworth\Effects\Query\QueryContext;
use Bga\Games\ProjectEdgeworth\Flow\Action\ProposalCounts;
use Bga\Games\ProjectEdgeworth\Flow\Action\ProposalPhase;
use Bga\Games\ProjectEdgeworth\Flow\Action\StepMode;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;

/**
 * M06 `PROPOSAL_COUNT_MOD`: 企画で引く枚数・残す枚数を変える（011 §2-3、007 §5-5 の ①〜③）。
 *
 * パラメータ（すべて省略可。加算）: `mainDraw`（メインの引く +）、`bonusDraw`（ボーナスの引く +）、`keep`（メイン・ボーナス共通の残す +）、
 * `bonusKeep`（ボーナスの残す +）、`bonusBase`（`['draw' => 6, 'keep' => 3]`。ボーナスの基本の枚数をこの値に置き換える。R011）。
 *
 * 置き換え（②）は「基本値との差」の加算として表す。修正を畳み込む順序に結果が依らず、加算（③）と重なる
 * （R011 + P071 → 引く 7・残す 4。FAQ 行 61）。
 */
final class ProposalCountMod implements ModifierPart
{
    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        return $host->playerId === $query->playerId;
    }

    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
    {
        if (!$value instanceof ProposalCounts) {
            throw new \InvalidArgumentException('PROPOSAL_COUNT_MOD folds ProposalCounts');
        }
        $bonus = $query->mode === StepMode::Bonus;
        $p = $spec->params;
        $draw = $value->draw;
        $keep = $value->keep + (int) ($p['keep'] ?? 0);
        if ($bonus) {
            $base = $p['bonusBase'] ?? null;
            if (is_array($base)) {
                $draw += (int) $base['draw'] - ProposalPhase::BONUS_DRAW;
                $keep += (int) $base['keep'] - ProposalPhase::BONUS_KEEP;
            }
            $draw += (int) ($p['bonusDraw'] ?? 0);
            $keep += (int) ($p['bonusKeep'] ?? 0);
        } else {
            $draw += (int) ($p['mainDraw'] ?? 0);
        }

        return new ProposalCounts($draw, $keep);
    }
}
