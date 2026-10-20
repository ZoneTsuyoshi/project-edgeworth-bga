<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

/**
 * プロジェクトカードの置き場所（010 §4.2）。
 */
enum CardLocation: string
{
    /** 共通の山札（裏向き）。枚数のみ公開 */
    case Deck = 'deck';
    /** 共通の捨て札（表向き） */
    case Discard = 'discard';
    /** 手札。location_arg = プレイヤー ID */
    case Hand = 'hand';
    /** 企画（V）で引いた直後の、残すか捨てるかを選ぶ前のカード。location_arg = プレイヤー ID */
    case Drawn = 'drawn';
    /** 衛星に配備されたカード。location_arg = sat_id、order = 配備の段（1〜4） */
    case Satellite = 'satellite';
}
