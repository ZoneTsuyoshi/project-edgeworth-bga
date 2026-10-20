<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Context;

use Bga\Games\ProjectEdgeworth\Material\Hook;
use Bga\Games\ProjectEdgeworth\Material\ProjectType;

/**
 * フックのイベントの内容（011 §3-1 の「イベントの内容」）。窓の中でトリガーした効果の文脈として `pool_context`（JSON）に保存する。
 *
 * - `playerId`: イベントの当事者。効果は同じプレイヤーのものにだけ反応する（007 §8-1）。null は全プレイヤーに起きるイベント
 *   （`PLANNING_REVEAL`・`ORBIT_START` など）で、全員のプールに積む。
 * - `satelliteId`: イベントの衛星（配備先・打ち上げた衛星・転回した衛星・通信確立した衛星・突入した衛星・着陸した衛星）。
 * - `cardId`・`cardType`: 配備されたカード（`DEPLOYED`）。`LAUNCHED` に `cardId` があれば、P035・R005 が打ち上げ時に配備したそのカードの
 *   打ち上げ時効果（`ON_LAUNCH`）だけを起こすイベント（`TriggerCollector::reacts()`）。
 */
final readonly class GameEvent
{
    /**
     * @param array<string, mixed> $data 上に無いイベント固有の値（起点、着陸した区域の種類、移動前後の地域など）
     */
    public function __construct(
        public Hook $hook,
        public ?int $playerId,
        public ?int $satelliteId = null,
        public ?int $cardId = null,
        public ?ProjectType $cardType = null,
        public ?int $cell = null,
        public int $generation = 0,
        public array $data = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'hook' => $this->hook->value,
            'player' => $this->playerId,
            'sat' => $this->satelliteId,
            'card' => $this->cardId,
            'type' => $this->cardType?->value,
            'cell' => $this->cell,
            'gen' => $this->generation,
            'data' => $this->data,
        ];
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function fromArray(array $a): self
    {
        return new self(
            Hook::from((string) $a['hook']),
            $a['player'] === null ? null : (int) $a['player'],
            $a['sat'] === null ? null : (int) $a['sat'],
            $a['card'] === null ? null : (int) $a['card'],
            $a['type'] === null ? null : ProjectType::from((string) $a['type']),
            $a['cell'] === null ? null : (int) $a['cell'],
            (int) $a['gen'],
            $a['data'],
        );
    }
}
