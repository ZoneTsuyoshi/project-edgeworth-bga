<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Pool;

use Bga\Games\ProjectEdgeworth\Effects\Condition\ConditionContext;
use Bga\Games\ProjectEdgeworth\Effects\Condition\ConditionEvaluator;
use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameEvent;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameView;
use Bga\Games\ProjectEdgeworth\Material\EffectCatalog;
use Bga\Games\ProjectEdgeworth\Material\EffectKind;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;
use Bga\Games\ProjectEdgeworth\Material\Hook;

/**
 * イベント → プールへの追加（011 §5-4）。効果は同じプレイヤーのイベントにだけ反応する（007 §8-1）。
 * `reacts()` は、トリガーの収集時と解決の開始時の再確認（007 §3-2）の両方で使う。
 */
final class TriggerCollector
{
    public function __construct(
        private readonly GameView $view,
        private readonly EffectCatalog $catalog,
        private readonly ConditionEvaluator $conditions,
    ) {
    }

    /**
     * イベントに反応する効果をプールに積む。積んだ要素を返す。
     *
     * @return list<PoolItem>
     */
    public function collect(WindowState $state, GameEvent $event): array
    {
        $players = $event->playerId === null ? $this->view->playerIds() : [$event->playerId];
        $added = [];
        foreach ($players as $playerId) {
            foreach ($this->view->hosts($playerId) as $host) {
                $spec = $this->catalog->specFor($host->cardKey);
                if ($spec === null || !$spec->isTriggered() || $spec->hook !== $event->hook) {
                    continue;
                }
                if (!$this->reacts($spec, $host, $event)) {
                    continue;
                }
                $item = PoolItem::forHost($state->nextId(), $host, $event);
                $state->add($item);
                $added[] = $item;
            }
        }

        return $added;
    }

    /**
     * この宿主の効果が、このイベントに（いま）反応するか。
     *
     * - 稼働条件: `ACTIVE_ONLY` なら宿主の衛星が稼働中（着陸猶予を含む）。
     * - 使用済み: `ACTIVATED` は今ラウンド使用済みなら反応しない。
     * - 自身の配備: `selfOnly`（`ON_DEPLOY`・`ON_LAUNCH`）は自分の配備・自分の衛星の打ち上げにだけ反応し、そうでない `DEPLOYED` の
     *   反応効果は、そのカード自身を配備したイベントには反応しない（007 §3-2、FAQ 行 11・16）。
     * - 条件 K01〜K13。
     */
    public function reacts(EffectSpec $spec, EffectHost $host, GameEvent $event): bool
    {
        if ($spec->activeOnly && !$host->active) {
            return false;
        }
        if ($spec->kind === EffectKind::Activated && $host->used) {
            return false;
        }
        $own = $event->hook === Hook::Deployed && $host->kind === 'P' && $event->cardId === $host->src;
        if ($spec->selfOnly) {
            $mine = $event->hook === Hook::Deployed
                ? $own
                : ($event->satelliteId !== null && $event->satelliteId === $host->satelliteId);
            if (!$mine) {
                return false;
            }
        } elseif ($own) {
            return false;
        }

        return $this->conditions->holds($spec, new ConditionContext($host, $event));
    }
}
