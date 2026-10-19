<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Context;

/**
 * 効果エンジンがゲーム状態を読むための口（011 §1 の 5 層目）。実装は `Data/`・`Flow/` 側（状態 30〜32 の結線のとき）。
 * 読み取りだけで、副作用を持たない。
 */
interface GameView
{
    /**
     * @return list<int> 席順
     */
    public function playerIds(): array;

    /**
     * プレイヤーの効果を持つカード全部（衛星カードと、衛星に配備されたプロジェクトカード）。
     *
     * @return list<EffectHost>
     */
    public function hosts(int $playerId): array;

    /** 解決の時点で引き直すための、1 枚の最新の状態（カードが無くなっていれば null） */
    public function host(string $kind, int $src): ?EffectHost;

    public function maya(int $playerId): int;

    /**
     * @return list<int> 手札のカード ID
     */
    public function hand(int $playerId): array;

    /** 手札 1 枚の売却額（SELL_PRICE の修正を含む。008 §2-6） */
    public function sellPrice(int $playerId): int;
}
