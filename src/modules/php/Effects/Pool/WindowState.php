<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Pool;

use Bga\Games\projectedgeworth\Effects\Context\StagedLinkRequest;
use Bga\Games\projectedgeworth\Effects\LogEntry;

/**
 * 開いている窓 1 つ分の状態（プール、プレイヤーが選んだ順序、解決した効果の数、溜めたログ、ステージされた通信確立）。
 * `toArray()` / `fromArray()` で JSON にでき、再読み込み・再接続のあとも同じ窓を続けられる（009 §6.3、010 §9.5）。
 * DB（`pool`・`staged_link`・`held_notif`）への対応づけは状態 30〜32 を結線するときに行う。
 */
final class WindowState
{
    /** @var array<int, PoolItem> id => 要素 */
    private array $items = [];

    /** @var array<int, int> 要素 ID => 順序の位置（プレイヤーが [Confirm order] で決めたもの） */
    private array $ranks = [];

    /** @var list<LogEntry> */
    private array $logs = [];

    /** @var list<StagedLinkRequest> */
    private array $staged = [];

    private int $nextId = 1;
    private int $resolved = 0;
    private bool $aborted = false;

    public function __construct(public readonly string $windowId)
    {
    }

    public function add(PoolItem $item): void
    {
        $this->items[$item->id] = $item;
    }

    public function nextId(): int
    {
        return $this->nextId++;
    }

    public function item(int $id): ?PoolItem
    {
        return $this->items[$id] ?? null;
    }

    /**
     * @return list<PoolItem> 追加順
     */
    public function items(): array
    {
        return array_values($this->items);
    }

    /**
     * @return list<PoolItem>
     */
    public function pending(int $playerId): array
    {
        return array_values(array_filter(
            $this->items,
            static fn(PoolItem $i): bool => $i->playerId === $playerId && $i->isPending(),
        ));
    }

    public function rank(int $itemId): ?int
    {
        return $this->ranks[$itemId] ?? null;
    }

    public function setRank(int $itemId, int $rank): void
    {
        $this->ranks[$itemId] = $rank;
    }

    public function countResolved(): int
    {
        return $this->resolved;
    }

    public function noteResolved(): void
    {
        $this->resolved++;
    }

    public function isAborted(): bool
    {
        return $this->aborted;
    }

    public function abort(): void
    {
        $this->aborted = true;
    }

    public function addLog(LogEntry $log): void
    {
        $this->logs[] = $log;
    }

    /**
     * @return list<LogEntry>
     */
    public function logs(): array
    {
        return $this->logs;
    }

    public function stage(StagedLinkRequest $request): void
    {
        $this->staged[] = $request;
    }

    /**
     * ステージされた通信確立（コミット前の予約）。取り出さない。
     *
     * @return list<StagedLinkRequest>
     */
    public function staged(): array
    {
        return $this->staged;
    }

    /**
     * ステージされた通信確立を取り出して空にする（バリアでコミットする分）。
     *
     * @return list<StagedLinkRequest>
     */
    public function takeStaged(): array
    {
        $staged = $this->staged;
        $this->staged = [];

        return $staged;
    }

    public function hasStaged(): bool
    {
        return $this->staged !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'window' => $this->windowId,
            'items' => array_map(static fn(PoolItem $i): array => $i->toArray(), array_values($this->items)),
            'ranks' => $this->ranks,
            'logs' => array_map(static fn(LogEntry $l): array => $l->toArray(), $this->logs),
            'staged' => array_map(static fn(StagedLinkRequest $s): array => [
                'player' => $s->playerId, 'sat' => $s->satelliteId, 'cell' => $s->cell, 'source' => $s->sourceKey, 'cost' => $s->cost,
            ], $this->staged),
            'nextId' => $this->nextId,
            'resolved' => $this->resolved,
            'aborted' => $this->aborted,
        ];
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function fromArray(array $a): self
    {
        $state = new self((string) $a['window']);
        foreach ($a['items'] as $row) {
            $state->add(PoolItem::fromArray($row));
        }
        foreach ($a['ranks'] as $id => $rank) {
            $state->ranks[(int) $id] = (int) $rank;
        }
        foreach ($a['logs'] as $row) {
            $state->logs[] = LogEntry::fromArray($row);
        }
        foreach ($a['staged'] as $row) {
            $state->staged[] = new StagedLinkRequest(
                (int) $row['player'],
                (int) $row['sat'],
                (int) $row['cell'],
                (string) $row['source'],
                $row['cost'],
            );
        }
        $state->nextId = (int) $a['nextId'];
        $state->resolved = (int) $a['resolved'];
        $state->aborted = (bool) $a['aborted'];

        return $state;
    }
}
