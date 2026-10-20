<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * 企画の「残すカードの選択」にオプション（P068・P070・P072）がないときの `ActionHooks` の既定の実装:
 * 残す枚数はちょうど（引いた枚数を超えない）基本の枚数。
 */
trait StandardProposalHooks
{
    public function validateProposal(int $playerId, bool $bonus, ProposalCounts $counts, array $drawn, array $keep, array $data): void
    {
        $need = min(count($drawn), $counts->keep);
        if (count($keep) !== $need) {
            throw new RuleViolation("Keep exactly $need card(s)");
        }
    }

    public function applyProposal(int $playerId, bool $bonus, array $data): void
    {
    }

    public function proposalExtras(int $playerId, bool $bonus, array $drawn): array
    {
        return [];
    }
}
