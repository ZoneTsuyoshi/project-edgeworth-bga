<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Setup;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Flow\Action\ActionServices;
use Bga\Games\projectedgeworth\Flow\Action\ActionStep;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Flow\Action\StepOutcome;
use Bga\Games\projectedgeworth\Flow\Action\StepResult;

/**
 * マリガン（ゲームオプション。001 §3-5、008 §8-1、009 の 13・14）。全員が同時に、捨てる枚数（0〜5 枚）を選ぶ。
 * choice_data は {"discard": [カード ID, …]}（010 §9.3）。0 枚 = [Keep all]。
 * 全員の確定の後（14）に、捨てるカードを全員分まとめて捨て札に置き、席順（基準プレイヤーから）に同じ枚数を山札から引き直す。
 * 捨てたカードは山札に戻さない（U-4）。1 回だけで、引き直したカードをさらにマリガンすることはない。
 */
final class MulliganStep implements ActionStep
{
    public function __construct(private readonly ActionServices $services)
    {
    }

    public function participants(StepMode $mode): array
    {
        return $this->services->players->playerIds();
    }

    public function canAct(int $playerId, StepMode $mode): bool
    {
        return $this->hand($playerId) !== [];
    }

    public function validate(int $playerId, StepMode $mode, array $data): void
    {
        $discard = $data['discard'] ?? null;
        if (!is_array($discard)) {
            throw new RuleViolation('Select the cards to discard');
        }
        $ids = array_values(array_map('intval', $discard));
        if (count($ids) !== count(array_unique($ids))) {
            throw new RuleViolation('The same card cannot be selected twice');
        }
        $hand = $this->hand($playerId);
        foreach ($ids as $id) {
            if (!in_array($id, $hand, true)) {
                throw new RuleViolation("Card $id is not in your hand");
            }
        }
    }

    /** zombie: [Keep all]（008 §8-1） */
    public function defaultChoice(int $playerId, StepMode $mode): array
    {
        return ['discard' => []];
    }

    public function resolve(StepMode $mode, array $choices): StepResult
    {
        $order = $this->services->players->playerIds();
        $discards = [];
        foreach ($order as $playerId) {
            $data = $choices[$playerId] ?? $this->defaultChoice($playerId, $mode);
            $this->validate($playerId, $mode, $data);
            $discards[$playerId] = array_values(array_map('intval', $data['discard']));
            $this->services->cards->discard($discards[$playerId]);
        }
        $outcomes = [];
        foreach ($order as $playerId) {
            $count = count($discards[$playerId]);
            if ($count === 0) {
                $outcomes[] = new StepOutcome($playerId, 'pass');
                continue;
            }
            $outcomes[] = new StepOutcome($playerId, 'mulligan', false, [
                'discarded' => $discards[$playerId],
                'drawn' => $this->services->cards->drawTop($playerId, $count),
            ]);
        }
        return new StepResult($outcomes);
    }

    /**
     * @return list<int>
     */
    public function hand(int $playerId): array
    {
        return $this->services->cards->idsIn(CardLocation::Hand, $playerId);
    }
}
