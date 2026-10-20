<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Pool;

use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameEvent;

/**
 * プールの要素 1 件 = 窓の中でトリガーした効果（011 §9）。`stage` は多段の効果（P087）の進み具合で、再接続しても途中から再開できる。
 */
final class PoolItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $playerId,
        public readonly string $cardKey,
        public readonly string $hostKind,
        public readonly int $hostSrc,
        public readonly GameEvent $event,
        public int $stage = 1,
        public PoolItemStatus $status = PoolItemStatus::Pending,
        /** @var array<string, mixed> 多段の効果が段のあいだで持ち越す値（P087: 1 段目で引いたカード）。保存する */
        public array $memo = [],
    ) {
    }

    public function hostKey(): string
    {
        return $this->hostKind . ':' . $this->hostSrc;
    }

    public function isPending(): bool
    {
        return $this->status === PoolItemStatus::Pending;
    }

    public static function forHost(int $id, EffectHost $host, GameEvent $event): self
    {
        return new self($id, $host->playerId, $host->cardKey, $host->kind, $host->src, $event);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'player' => $this->playerId,
            'card' => $this->cardKey,
            'kind' => $this->hostKind,
            'src' => $this->hostSrc,
            'event' => $this->event->toArray(),
            'stage' => $this->stage,
            'status' => $this->status->value,
            'memo' => $this->memo,
        ];
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function fromArray(array $a): self
    {
        return new self(
            (int) $a['id'],
            (int) $a['player'],
            (string) $a['card'],
            (string) $a['kind'],
            (int) $a['src'],
            GameEvent::fromArray($a['event']),
            (int) $a['stage'],
            PoolItemStatus::from((string) $a['status']),
            (array) ($a['memo'] ?? []),
        );
    }
}
