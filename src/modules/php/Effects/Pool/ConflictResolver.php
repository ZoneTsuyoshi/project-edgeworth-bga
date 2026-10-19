<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Pool;

use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;
use Bga\Games\ProjectEdgeworth\Material\ResourceScope;
use Bga\Games\ProjectEdgeworth\Material\ResourceSymbol;
use Bga\Games\ProjectEdgeworth\Material\ResourceTouch;

/**
 * 資源の衝突の判定（007 §6-2、011 §6-4）。衝突が連なる効果を 1 つの集合（連結成分）にまとめる。
 *
 * 2 つの効果が衝突するのは、触れる資源のどれかの組が `ResourceTouch::conflictsWith()` を満たすとき。それに加えて、
 * 稼働条件 `ACTIVE_ONLY` の効果は宿主の衛星の稼働状態を読むので、稼働状態を書く効果（`A`、P024）と衝突する。
 */
final class ConflictResolver
{
    /**
     * @param array<int, array{PoolItem, EffectSpec, EffectHost}> $entries 要素 ID => [要素, 定義, 宿主]
     * @return list<list<int>> 2 件以上の衝突の集合（要素 ID の昇順）。集合の並びは先頭の要素 ID の昇順
     */
    public function sets(array $entries): array
    {
        $ids = array_keys($entries);
        sort($ids);
        $touches = [];
        foreach ($ids as $id) {
            [$item, $spec, $host] = $entries[$id];
            $touches[$id] = array_map(
                static fn(ResourceTouch $t): ResourceTouch => $t->bind($host->satelliteId, $item->event->satelliteId),
                $spec->touches,
            );
        }

        $parent = array_combine($ids, $ids);
        $find = static function (int $x) use (&$parent, &$find): int {
            return $parent[$x] === $x ? $x : ($parent[$x] = $find($parent[$x]));
        };
        foreach ($ids as $i => $a) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $b = $ids[$j];
                if ($this->conflict($touches[$a], $entries[$a][1], $touches[$b], $entries[$b][1])) {
                    $parent[$find($b)] = $find($a);
                }
            }
        }

        $groups = [];
        foreach ($ids as $id) {
            $groups[$find($id)][] = $id;
        }
        $sets = array_values(array_filter($groups, static fn(array $g): bool => count($g) >= 2));
        usort($sets, static fn(array $x, array $y): int => $x[0] <=> $y[0]);

        return $sets;
    }

    /**
     * @param list<ResourceTouch> $touchesA
     * @param list<ResourceTouch> $touchesB
     */
    private function conflict(array $touchesA, EffectSpec $a, array $touchesB, EffectSpec $b): bool
    {
        foreach ($touchesA as $x) {
            foreach ($touchesB as $y) {
                if ($x->conflictsWith($y)) {
                    return true;
                }
            }
        }

        return ($a->activeOnly && $this->writesActivity($touchesB)) || ($b->activeOnly && $this->writesActivity($touchesA));
    }

    /**
     * @param list<ResourceTouch> $touches
     */
    private function writesActivity(array $touches): bool
    {
        foreach ($touches as $t) {
            // 稼働状態を書く効果は P024 だけ（衛星を選ぶので全衛星）。宿主の衛星への読みとは常に重なるとみなす
            if ($t->symbol === ResourceSymbol::Activity && $t->scope !== ResourceScope::Player) {
                return true;
            }
        }

        return false;
    }
}
