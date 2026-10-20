<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Context;

use Bga\Games\projectedgeworth\Material\SatelliteShape;

/**
 * 効果を持つカード 1 枚（プロジェクトカードまたは衛星カード）の、その時点の状態。`GameView` が作る。
 *
 * - `kind`・`src`: 'P' = プロジェクトカード（`src` = `card_id`）、'S' = 衛星カード（`src` = `sat_id`）。010 §9.5 の `pool_kind`・`pool_src`。
 * - `deployOrder`: 衛星カードは 0、配備されたカードは下の段から 1, 2, …（デフォルトの順。007 §6-3）。
 * - `active`: 効果を持つカードのある衛星が稼働中か。着陸猶予の間は稼働中として扱う（007 §3-2、FAQ 行 32）。
 * - `used`: 起動効果の使用済み（今ラウンド）。
 */
final readonly class EffectHost
{
    public function __construct(
        public string $kind,
        public int $src,
        public string $cardKey,
        public int $playerId,
        public ?int $satelliteId,
        public ?SatelliteShape $shape,
        public int $deployOrder,
        public bool $active,
        public bool $used,
    ) {
    }

    /** 窓の中でカードを識別する鍵（'P:13' など） */
    public function key(): string
    {
        return $this->kind . ':' . $this->src;
    }
}
