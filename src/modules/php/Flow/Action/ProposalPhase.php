<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

use Bga\Games\ProjectEdgeworth\Data\CardLocation;

/**
 * V 企画（001 §7-6、008 §3-8、009 の 90・91）。
 *
 * メイン: 2 枚引いて 1 枚残す。ボーナス（V を選んだプレイヤー）: メインの代わりに 5 枚引いて 2 枚残す（効果で増減）。
 * 1. drawAll(): 席順に直列で全員分を引く（引いたカードは drawn に置く。取り消せない）。
 * 2. 全員が残すカードを選ぶ（ActionStep の枠組み。choice_data は {"keep": [ID, …]}）。選び直しは確定まで可。
 * 3. resolve(): 残すカードを手札へ、残りを捨て札へ。企画で捨てるカードは売却ではない（売却額の修正は働かない）。
 * 山札と捨て札が尽きて引ける枚数が足りないときは、残す枚数は引いた枚数を超えない。
 */
final class ProposalPhase implements ActionStep
{
    public const ACTION = 5;
    public const MAIN_DRAW = 2;
    public const MAIN_KEEP = 1;
    public const BONUS_DRAW = 5;
    public const BONUS_KEEP = 2;

    public function __construct(private readonly ActionServices $services)
    {
    }

    /** V のボーナス保持者か（保持者は「5 枚引いて 2 枚残す」を、メインの代わりに行う） */
    public function isBonusHolder(int $playerId): bool
    {
        return in_array($playerId, $this->services->bonusHolders(self::ACTION), true);
    }

    /** 引く枚数・残す枚数（効果による修正を含む） */
    public function counts(int $playerId, bool $bonus): ProposalCounts
    {
        $base = $bonus
            ? new ProposalCounts(self::BONUS_DRAW, self::BONUS_KEEP)
            : new ProposalCounts(self::MAIN_DRAW, self::MAIN_KEEP);
        $counts = $this->services->hooks->proposalCounts($playerId, $bonus, $base);
        return new ProposalCounts(max(0, $counts->draw), max(0, $counts->keep));
    }

    /**
     * ステップ 1: 全員がカードを引く。
     *
     * @return array<int, list<int>> プレイヤー ID => 引いたカード（引いた順。席順）
     */
    public function drawAll(): array
    {
        $drawn = [];
        foreach ($this->services->players->playerIds() as $playerId) {
            $counts = $this->counts($playerId, $this->isBonusHolder($playerId));
            $drawn[$playerId] = $this->services->cards->drawTop($playerId, $counts->draw, CardLocation::Drawn);
        }
        return $drawn;
    }

    /**
     * 引いたカード（引いた順）。
     *
     * @return list<int>
     */
    public function drawn(int $playerId): array
    {
        return $this->services->cards->idsIn(CardLocation::Drawn, $playerId);
    }

    /** 残す枚数（引いた枚数を超えない） */
    public function keepCount(int $playerId): int
    {
        return min($this->counts($playerId, $this->isBonusHolder($playerId))->keep, count($this->drawn($playerId)));
    }

    public function participants(StepMode $mode): array
    {
        return $this->services->players->playerIds();
    }

    public function canAct(int $playerId, StepMode $mode): bool
    {
        return $this->drawn($playerId) !== [];
    }

    public function validate(int $playerId, StepMode $mode, array $data): void
    {
        $keep = $data['keep'] ?? null;
        if (!is_array($keep)) {
            throw new RuleViolation('Select the cards to keep');
        }
        $keep = array_values(array_map('intval', $keep));
        if (count($keep) !== count(array_unique($keep))) {
            throw new RuleViolation('The same card cannot be kept twice');
        }
        $drawn = $this->drawn($playerId);
        foreach ($keep as $id) {
            if (!in_array($id, $drawn, true)) {
                throw new RuleViolation("Card $id was not drawn");
            }
        }
        $need = $this->keepCount($playerId);
        if (count($keep) !== $need) {
            throw new RuleViolation("Keep exactly $need card(s)");
        }
    }

    /** zombie: 引いた順の先頭から必要な枚数を残す（008 §3-8） */
    public function defaultChoice(int $playerId, StepMode $mode): array
    {
        return ['keep' => array_slice($this->drawn($playerId), 0, $this->keepCount($playerId))];
    }

    public function resolve(StepMode $mode, array $choices): StepResult
    {
        $outcomes = [];
        foreach ($this->services->players->playerIds() as $playerId) {
            $drawn = $this->drawn($playerId);
            if ($drawn === []) {
                $outcomes[] = new StepOutcome($playerId, 'pass');
                continue;
            }
            $data = $choices[$playerId] ?? $this->defaultChoice($playerId, $mode);
            $this->validate($playerId, $mode, $data);
            $keep = array_values(array_map('intval', $data['keep']));
            $discard = array_values(array_diff($drawn, $keep));
            $this->services->cards->keepDrawn($playerId, $keep);
            $this->services->cards->discard($discard);
            $outcomes[] = new StepOutcome($playerId, 'keep', false, [
                'drawn' => count($drawn),
                'kept' => $keep,
                'discarded' => $discard,
                'bonus' => $this->isBonusHolder($playerId),
            ]);
        }
        return new StepResult($outcomes);
    }
}
