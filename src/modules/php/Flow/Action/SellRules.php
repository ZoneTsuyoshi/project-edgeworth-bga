<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Data\PlayerValues;
use Bga\Games\projectedgeworth\Data\ProjectCardRepository;

/**
 * 売却（手札 1 枚を捨てて 3 マヤ。001 §11-1、008 §2-6）と、支払い不足の売却の同梱。
 * 売却額は ActionHooks::sellPrice() で変わり得る（P077・R009）。企画で捨てるカードは売却ではないので、ここを通さない。
 */
final class SellRules
{
    public const BASE_PRICE = 3;

    public function __construct(
        private readonly ProjectCardRepository $cards,
        private readonly PlayerValues $players,
        private readonly ActionHooks $hooks,
    ) {
    }

    /** 手札 1 枚あたりの売却額（args の sellValue。クライアントは計算しない） */
    public function price(int $playerId): int
    {
        return max(0, $this->hooks->sellPrice($playerId, self::BASE_PRICE));
    }

    /**
     * @param list<int> $reserved 選択に含まれていて売れないカード（配備するカードなど。二重売却の防止）
     */
    public function assess(int $playerId, int $cost, array $reserved = []): Payability
    {
        $hand = $this->cards->idsIn(CardLocation::Hand, $playerId);
        $sellable = count(array_diff($hand, $reserved));
        return new Payability($cost, $this->players->maya($playerId), $this->price($playerId), $sellable);
    }

    /**
     * 売却するカードの検査（重複なし・本人の手札・予約済みでない）。
     *
     * @param list<int> $ids
     * @param list<int> $reserved
     * @throws RuleViolation
     */
    public function validate(int $playerId, array $ids, array $reserved = []): void
    {
        if (count($ids) !== count(array_unique($ids))) {
            throw new RuleViolation('The same card cannot be sold twice');
        }
        $hand = $this->cards->idsIn(CardLocation::Hand, $playerId);
        foreach ($ids as $id) {
            if (!in_array($id, $hand, true)) {
                throw new RuleViolation("Card $id is not in your hand");
            }
            if (in_array($id, $reserved, true)) {
                throw new RuleViolation("Card $id is part of your choice and cannot be sold");
            }
        }
    }

    /**
     * 独立の売却（[Sell cards]）。取り消せない。
     *
     * @param list<int> $ids
     * @param list<int> $reserved 自分の確定内容に含まれるカード
     * @return int 得たマヤ
     * @throws RuleViolation
     */
    public function sell(int $playerId, array $ids, array $reserved = []): int
    {
        if ($ids === []) {
            throw new RuleViolation('Select at least one card to sell');
        }
        $this->validate($playerId, $ids, $reserved);
        return $this->sellAll($playerId, $ids);
    }

    /**
     * 選択に同梱した売却を行ってからコストを払う。売却しても払えない、または売却の内容が成り立たないときは何もせず null
     * （その行動は [Pass] として処理する。008 の暫定 No.5）。
     *
     * @param list<int> $sellIds
     * @param list<int> $reserved
     */
    public function tryPay(int $playerId, int $cost, array $sellIds, array $reserved = []): ?Payment
    {
        try {
            $this->validate($playerId, $sellIds, $reserved);
        } catch (RuleViolation) {
            return null;
        }
        $price = $this->price($playerId);
        if ($this->players->maya($playerId) + $price * count($sellIds) < $cost) {
            return null;
        }
        $gained = $sellIds === [] ? 0 : $this->sellAll($playerId, $sellIds);
        if ($cost > 0) {
            $this->players->addMaya($playerId, -$cost);
        }
        return new Payment($playerId, $cost, $sellIds, $gained);
    }

    /**
     * 確定時の検査: 売却を使って払えること（売りすぎは許す。008 §2-6 の「その枚数以上」）。
     *
     * @param list<int> $sellIds
     * @param list<int> $reserved
     * @throws RuleViolation
     */
    public function assertPayable(int $playerId, int $cost, array $sellIds, array $reserved = []): void
    {
        $this->validate($playerId, $sellIds, $reserved);
        $short = $cost - $this->players->maya($playerId);
        if ($short > 0 && $this->price($playerId) * count($sellIds) < $short) {
            throw new RuleViolation("Not enough Maya (short $short)");
        }
    }

    /**
     * @param list<int> $ids
     */
    private function sellAll(int $playerId, array $ids): int
    {
        $gained = $this->price($playerId) * count($ids);
        $this->cards->discard($ids);
        if ($gained > 0) {
            $this->players->addMaya($playerId, $gained);
        }
        return $gained;
    }
}
