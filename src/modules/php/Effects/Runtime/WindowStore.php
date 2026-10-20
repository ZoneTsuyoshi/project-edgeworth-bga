<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Runtime;

use Bga\Games\projectedgeworth\Data\Database;
use Bga\Games\projectedgeworth\Data\GlobalValues;
use Bga\Games\projectedgeworth\Data\PoolEntry;
use Bga\Games\projectedgeworth\Data\PoolRepository;
use Bga\Games\projectedgeworth\Data\PoolStatus;
use Bga\Games\projectedgeworth\Data\StagedLinkRepository;

/**
 * 効果エンジンの窓の状態（`WindowState::toArray()`）を DB に保存し、復元する（010 §9.5、§5.3。再読み込み・再接続に耐える）。
 *
 * - `pool`: プールの要素 1 件 = 1 行（ID はエンジンが振ったもの）。`pool_context` にイベント（JSON）、`pool_chosen_order` に選んだ順序の位置、
 *   `pool_state` に段とメモ。
 * - `staged_link`: ステージされた通信確立（コミット前の予約）。
 * - `window_effects`（グローバル値）: 解決した効果の数。強制終了したかは `flow_ctx` の `windowAborted`。
 * ログは保存しない（呼び出し側が、保存の前に通知にして `held_notif` へ移す）。
 */
final class WindowStore
{
    private readonly PoolRepository $pool;
    private readonly StagedLinkRepository $staged;

    public function __construct(Database $db, private readonly GlobalValues $globals)
    {
        $this->pool = new PoolRepository($db);
        $this->staged = new StagedLinkRepository($db);
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function save(array $snapshot): void
    {
        $this->pool->clear();
        $this->staged->clear();
        $window = (string) $snapshot['window'];
        $ranks = $snapshot['ranks'];
        foreach ($snapshot['items'] as $i => $item) {
            $this->pool->insert(new PoolEntry(
                (int) $item['id'],
                (int) $item['player'],
                $window,
                (string) $item['kind'],
                (int) $item['src'],
                (string) $item['card'],
                (string) $item['event']['hook'],
                $item['event'],
                $i,
                isset($ranks[$item['id']]) ? (int) $ranks[$item['id']] : null,
                PoolStatus::from(strtolower((string) $item['status'])),
                ['stage' => (int) $item['stage'], 'memo' => $item['memo']],
            ));
        }
        foreach ($snapshot['staged'] as $link) {
            $cost = $link['cost'];
            $this->staged->stage(
                (int) $link['player'],
                (int) $link['cell'],
                (int) $link['sat'],
                (int) ($cost['special'] ?? 0) > 0,
                (string) $link['source'],
                (int) ($cost['area'] ?? 0),
                (int) ($cost['special'] ?? 0),
                $cost['pioneer'] ?? [],
            );
        }
        $this->globals->setWindowEffects((int) $snapshot['resolved']);
        $context = $this->globals->flowContext();
        if ($snapshot['aborted']) {
            $context['windowAborted'] = true;
        } else {
            unset($context['windowAborted']);
        }
        $this->globals->setFlowContext($context);
    }

    /**
     * @return array<string, mixed>|null 窓が開いていなければ null
     */
    public function load(): ?array
    {
        $window = $this->globals->windowId();
        if ($window === null) {
            return null;
        }
        $items = [];
        $ranks = [];
        $nextId = 1;
        foreach ($this->pool->all() as $entry) {
            $items[] = [
                'id' => $entry->id,
                'player' => $entry->playerId,
                'card' => $entry->effect,
                'kind' => $entry->kind,
                'src' => $entry->src,
                'event' => $entry->context ?? [],
                'stage' => (int) ($entry->state['stage'] ?? 1),
                'status' => strtoupper($entry->status->value),
                'memo' => (array) ($entry->state['memo'] ?? []),
            ];
            if ($entry->chosenOrder !== null) {
                $ranks[$entry->id] = $entry->chosenOrder;
            }
            $nextId = max($nextId, $entry->id + 1);
        }
        usort($items, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);
        $staged = [];
        foreach ($this->staged->all() as $link) {
            $staged[] = [
                'player' => $link->playerId,
                'sat' => $link->satId,
                'cell' => $link->cell,
                'source' => $link->source,
                'cost' => [
                    'base' => $link->areaCost,
                    'reef' => 0,
                    'reduction' => 0,
                    'area' => $link->areaCost,
                    'pioneer' => $link->pioneer,
                    'special' => $link->specialCost,
                ],
            ];
        }

        return [
            'window' => $window,
            'items' => $items,
            'ranks' => $ranks,
            'logs' => [],
            'staged' => $staged,
            'nextId' => $nextId,
            'resolved' => $this->globals->windowEffects(),
            'aborted' => (bool) ($this->globals->flowContext()['windowAborted'] ?? false),
        ];
    }

    /** 窓を閉じるときの全消去 */
    public function clear(): void
    {
        $this->pool->clear();
        $this->staged->clear();
        $context = $this->globals->flowContext();
        unset($context['windowAborted']);
        $this->globals->setFlowContext($context);
    }
}
