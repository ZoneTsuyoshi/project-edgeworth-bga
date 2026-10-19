<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Scoring;

/**
 * ゲーム終了時の手札の自動換金（001 §10、Q-12）。1 枚 3 マヤ。
 * P077「オライオンリワード」と R009（CONCORDE・PHOBOS）は稼働中なら 1 枚につき +2（加算。007 未確定 14 の暫定案で適用）。
 */
final class HandCashOut
{
    public const BASE = 3;
    public const BONUS = 2;
    public const BONUS_PROJECT_CARD = 'P077';
    public const BONUS_SATELLITE_CARD = 'R009';

    private function __construct()
    {
    }

    /** 1 枚あたりの売却額（終了時の衛星の稼働状態で評価する） */
    public static function pricePerCard(PlayerState $player): int
    {
        $price = self::BASE;
        foreach ($player->satellites as $sat) {
            if (!$sat->active) {
                continue;
            }
            if ($sat->cardId === self::BONUS_SATELLITE_CARD) {
                $price += self::BONUS;
            }
            if ($sat->isDeployed(self::BONUS_PROJECT_CARD)) {
                $price += self::BONUS;
            }
        }
        return $price;
    }

    public static function mayaFor(PlayerState $player): int
    {
        return $player->handCount * self::pricePerCard($player);
    }
}
