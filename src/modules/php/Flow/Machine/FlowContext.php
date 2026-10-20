<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Board\RandomSource;
use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Data\Database;
use Bga\Games\projectedgeworth\Data\GlobalValues;
use Bga\Games\projectedgeworth\Data\LinkRepository;
use Bga\Games\projectedgeworth\Data\MissionRepository;
use Bga\Games\projectedgeworth\Data\RegionRepository;
use Bga\Games\projectedgeworth\Flow\FinalScoring;
use Bga\Games\projectedgeworth\Flow\Action\ActionHooks;
use Bga\Games\projectedgeworth\Flow\Action\ActionPhases;
use Bga\Games\projectedgeworth\Effects\Runtime\EffectRuntime;
use Bga\Games\projectedgeworth\Flow\Action\ActionServices;
use Bga\Games\projectedgeworth\Flow\NoOrbitModifiers;
use Bga\Games\projectedgeworth\Flow\OrbitModifiers;

/**
 * 状態機械のドライバが共有する部品（DB・グローバル値・ルール層・通知の組み立て・窓の問い合わせ）。BGA の Table に依存しない。
 * Game が 1 つ作って状態クラスに配る。テストは PDO の Database と ArrayGlobals で作る。
 */
final class FlowContext
{
    public readonly ActionPhases $phases;
    public readonly ActionServices $services;
    public readonly Notices $notices;
    public readonly WindowGate $gate;
    public readonly OrbitModifiers $orbitMods;

    /**
     * @param \Closure(int): string $nameOf プレイヤー ID => プレイヤー名
     * @param ActionHooks|null $hooks カード効果の問い合わせ口。null は効果なし（`$effects` があればそちらの `hooks` を使う）
     * @param EffectRuntime|null $effects 本番の効果エンジンの組み立て（ゲート・フック・循環の修正を渡す）。null は効果なし
     */
    public function __construct(
        public readonly Database $db,
        public readonly GlobalValues $globals,
        public readonly RandomSource $random,
        \Closure $nameOf,
        WindowGate $gate = new NoEffectWindowGate(),
        ?ActionHooks $hooks = null,
        public readonly ?EffectRuntime $effects = null,
        /** ゲームオプション 105 が Visible か（ミッション以外の名声を公開する） */
        public readonly bool $publicFame = false,
    ) {
        $this->gate = $effects === null ? $gate : $effects->gate;
        $this->orbitMods = $effects === null ? new NoOrbitModifiers() : $effects->orbitMods;
        $this->phases = ActionPhases::load($db, $globals, $random, $effects === null ? $hooks : $effects->hooks, $effects?->layout);
        $this->services = $this->phases->services();
        $effects?->attach($this->services);
        $this->notices = new Notices($nameOf);
    }

    /**
     * ドライバが起こした窓フックのイベントを台帳に記録する（効果エンジンがなければ何もしない）。
     */
    public function record(\Bga\Games\projectedgeworth\Effects\Context\GameEvent ...$events): void
    {
        $this->effects?->ledger->record(...$events);
    }

    public function finalScoring(): FinalScoring
    {
        $s = $this->services;
        return new FinalScoring(
            $s->satellites,
            $s->cards,
            $s->links,
            new MissionRepository($this->db),
            new RegionRepository($this->db),
            $s->players,
            $s->globals,
        );
    }

    /**
     * 値が変わりうる処理の最後に送る再同期の通知: N33 と、ゲームオプション 105 が Visible のときの N36。
     *
     * @return list<Notice>
     */
    public function sync(): array
    {
        // 窓の最中は他人の変化をバリアまで見せない（010 §9.4）ので、N36 は窓を閉じた後（windowClosed の後）だけ送る
        if (!$this->publicFame || $this->globals->windowId() !== null) {
            return [$this->counters()];
        }
        return [$this->counters(), $this->notices->publicFameUpdated($this->finalScoring()->publicFame())];
    }

    /**
     * N33: 山札・捨て札・手札・引いた直後・残りキューブの枚数の再同期。
     */
    public function counters(): Notice
    {
        $players = [];
        foreach ($this->services->players->playerIds() as $playerId) {
            $players[$playerId] = [
                'handCount' => $this->services->cards->countIn(CardLocation::Hand, $playerId),
                'drawnCount' => $this->services->cards->countIn(CardLocation::Drawn, $playerId),
                'cubesLeft' => LinkRepository::CUBES_PER_PLAYER - $this->services->links->countFor($playerId),
            ];
        }
        return $this->notices->countersUpdate($this->deckCount(), $this->discardCount(), $players);
    }

    public function deckCount(): int
    {
        return $this->services->cards->countIn(CardLocation::Deck);
    }

    public function discardCount(): int
    {
        return $this->services->cards->countIn(CardLocation::Discard);
    }

    /**
     * カードを引く処理の前に呼び、後で山札の再構成があったか（N11）を調べるための印。
     */
    public function reshuffleMark(): int
    {
        return $this->services->cards->reshuffles();
    }

    /**
     * @return list<Notice> 再構成があれば N11
     */
    public function reshuffleNotices(int $mark): array
    {
        return $this->services->cards->reshuffles() > $mark ? [$this->notices->deckReshuffled($this->deckCount())] : [];
    }
}
