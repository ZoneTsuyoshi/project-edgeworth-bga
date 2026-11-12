<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

/**
 * deploy_stage テーブルの 1 行（009 追補 2026-11-12、010 追補）: II 配備でボーナス保持者が [Deploy another card] を押した時点で
 * 先に確定させた 1 枚目の記録。バリア（全員確定）まで他人に見せない。
 */
final class DeployStage
{
    /**
     * @param list<int> $sold 1 枚目の支払いのために売却したカード
     * @param bool $irreversible 山札から引いたなど、後戻りできない結果が出たか（真なら [Reset] 不可）
     * @param array<string, mixed> $snapshot ステージ前の復元用スナップショット（`DeployStageSnapshot`）
     * @param array<string, mixed> $publicBefore ステージ前の公開値（`PublicView::snapshotData()`。他人向けにステージ分を差し引くため）
     * @param list<array<string, mixed>> $resolved ステージで起こしたイベント（`GameEvent::toArray()`）。`data.resolved` に、
     *        ステージで解決済みの宿主の鍵を持つ。バリアのあと窓 DE に渡し、解決済みの（イベント、効果）の組を飛ばす
     */
    public function __construct(
        public readonly int $playerId,
        public readonly int $card,
        public readonly int $satId,
        public readonly int $tier,
        public readonly int $paid,
        public readonly array $sold,
        public readonly bool $irreversible,
        public readonly array $snapshot,
        public readonly array $publicBefore,
        public readonly array $resolved,
    ) {
    }
}
