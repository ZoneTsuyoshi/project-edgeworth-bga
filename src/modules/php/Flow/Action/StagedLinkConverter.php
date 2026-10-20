<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

use Bga\Games\projectedgeworth\Effects\Context\GameEvent;
use Bga\Games\projectedgeworth\Effects\Context\StagedLinkRequest;
use Bga\Games\projectedgeworth\Material\Hook;

/**
 * 状態 32 のバリアで、窓の中でステージされた通信確立（効果による通信確立。R11）を `LinkCommitter` に渡す形へ変換し、
 * コミットの結果を `LINKED` のイベントに直す（007 §7-1・§7-3）。
 *
 * - コストはステージ時に確定して予約したもの（`StagedLinkRequest::$cost` = `LinkCost::toArray()`）をそのまま使う。同じバリアでコミットする
 *   依頼のコストは、すべてコミット前の盤面で作られているので、同じ世代の同じ区域を通信確立する相手とは先駆コストを払い合わない。
 *   前の世代でコミットされた区域は、次の世代でステージされる時点でキューブがあるので、先駆コストが掛かる（FAQ 行 42、RB p.20）。
 * - 売却はステージの時点（[Use] の答えの同梱）で済んでいるので、依頼は売却を持たない。
 *
 * 呼び出しの流れ（32 の 1 周）: `$report = $engine->barrier()` → `$report->stagedLinks` があれば
 * `$commit = $committer->commit(StagedLinkConverter::requests($report->stagedLinks))` → `StagedLinkConverter::events($commit, $generation)` を
 * `$engine->collect()` で積む → もう一度 `$engine->barrier()`。ステージが空になるまで繰り返す。
 */
final class StagedLinkConverter
{
    private function __construct()
    {
    }

    /**
     * @param list<StagedLinkRequest> $staged
     * @return list<LinkRequest>
     */
    public static function requests(array $staged): array
    {
        $requests = [];
        foreach ($staged as $link) {
            $cost = LinkCost::fromArray($link->cost);
            $requests[] = new LinkRequest($link->playerId, $link->satelliteId, $link->cell, $cost->specialCost > 0, $cost);
        }

        return $requests;
    }

    /**
     * コミットされた通信確立の `LINKED` イベント（席順）。コストを払えず取りやめた依頼はイベントを起こさない。
     * 特殊通信確立も「通信確立したとき」を起こす（007 未確定 8）。
     *
     * @param int $generation 世代（IV のステップ = 0、バリアの 1 周目 = 1、…）
     * @return list<GameEvent>
     */
    public static function events(LinkCommit $commit, int $generation): array
    {
        $events = [];
        foreach ($commit->entries as $entry) {
            $request = $entry->request;
            $events[] = new GameEvent(
                Hook::Linked,
                $request->playerId,
                $request->satelliteId,
                cell: $request->cell,
                generation: $generation,
                data: ['special' => $request->special],
            );
        }

        return $events;
    }
}
