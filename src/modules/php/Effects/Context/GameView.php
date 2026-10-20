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

    // ---- E-2 で足した読み取り ----

    /** 衛星に配備されている DEVICE の枚数（P039・P042。解決の時点で数える。衛星カードは数えない） */
    public function deviceCountOn(int $satelliteId): int;

    /** いま企画フェイズか（企画フェイズ中に引いたカードを `PROPOSAL_DRAWN` で数えるため。P075。011 §6-5） */
    public function isProposalPhase(): bool;

    /** 区域 `$cell` が、プレイヤーが通信確立済みの区域か（発射拠点の区域を含む。FAQ 行 44。K07） */
    public function isOwnLinkedArea(int $playerId, int $cell): bool;

    // ---- E-3 で足した読み取り ----

    /** 衛星に配備されているプロジェクトカードの枚数（P036: 打ち上げる衛星のカード 1 枚につき。衛星カードは数えない） */
    public function cardsOn(int $satelliteId): int;

    /**
     * そのアクションのボーナスを、EUROPA の宣言で得ているか（K12。自分で選んで得たなら偽。R011 は宣言で得たボーナスには効かない。FAQ 行 71）。
     *
     * @param string $action `ActionKind` の値
     */
    public function declaredBonus(int $playerId, string $action): bool;

    /**
     * 燃料の回復で実際に動くか（消費量 c が 1 以上で、燃料庫の中 c <= C にある）。満タン（c = 0）と庫外（燃料切れ c > C）の衛星は
     * 回復しても動かないので偽（R05。燃料回復の効果は発動した扱いにしない）。
     */
    public function canRecoverFuel(int $satelliteId): bool;
}
