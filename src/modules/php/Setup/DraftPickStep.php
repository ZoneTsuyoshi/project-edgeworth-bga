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
 * 初期手札のドラフト（ゲームオプション。013、008 §8-2、009 の 15・16）。全員が同時に、自分の前のパックから 1 枚を選ぶ。
 * choice_data は {"card": ID}（010 §9.3）。全員の確定の後（16）に、選んだカードを手札へ入れ、残りを席順の次の人へ渡す。
 */
final class DraftPickStep implements ActionStep
{
    /** 選ぶ回数（5 枚のパックから 4 回。最後の 1 枚は渡されて自動で手札に入る） */
    public const PICKS = GameSetup::HAND_SIZE - 1;

    public function __construct(private readonly ActionServices $services)
    {
    }

    public function participants(StepMode $mode): array
    {
        return $this->services->players->playerIds();
    }

    public function canAct(int $playerId, StepMode $mode): bool
    {
        return $this->pack($playerId) !== [];
    }

    public function validate(int $playerId, StepMode $mode, array $data): void
    {
        $card = $data['card'] ?? null;
        if (!is_int($card)) {
            throw new RuleViolation(clienttranslate('Select one card to keep'));
        }
        if (!in_array($card, $this->pack($playerId), true)) {
            throw new RuleViolation(clienttranslate('You cannot choose card ${id}'), ['id' => $card]);
        }
    }

    /** zombie: パックの先頭（order の最小。008 §9-1） */
    public function defaultChoice(int $playerId, StepMode $mode): array
    {
        $pack = $this->pack($playerId);
        if ($pack === []) {
            throw new \LogicException("player $playerId has no draft pack");
        }
        return ['card' => $pack[0]];
    }

    public function resolve(StepMode $mode, array $choices): StepResult
    {
        $order = $this->services->players->playerIds();
        $picked = [];
        foreach ($order as $playerId) {
            $data = $choices[$playerId] ?? $this->defaultChoice($playerId, $mode);
            $this->validate($playerId, $mode, $data);
            $picked[$playerId] = (int) $data['card'];
        }
        $outcomes = [];
        foreach ($order as $playerId) {
            $this->services->cards->takeFromPack($playerId, $picked[$playerId]);
            $outcomes[] = new StepOutcome($playerId, 'draft', false, ['card' => $picked[$playerId]]);
        }
        return new StepResult($outcomes);
    }

    /**
     * @return list<int> パックのカード（先頭が zombie の既定）
     */
    public function pack(int $playerId): array
    {
        return $this->services->cards->idsIn(CardLocation::Pack, $playerId);
    }

    /**
     * 各自のパックの枚数。全員同じでなければ整合性の誤り。
     */
    public function packSize(): int
    {
        $sizes = [];
        foreach ($this->services->players->playerIds() as $playerId) {
            $sizes[] = $this->services->cards->countIn(CardLocation::Pack, $playerId);
        }
        if (count(array_unique($sizes)) !== 1) {
            throw new \LogicException('draft packs have different sizes: ' . implode(',', $sizes));
        }
        return $sizes[0];
    }

    /** 何回目か（1〜4）。新しいグローバル値は持たず、パックの枚数から求める（013 §3.1） */
    public function pickNo(): int
    {
        return GameSetup::HAND_SIZE - $this->packSize() + 1;
    }
}
