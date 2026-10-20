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
use Bga\Games\projectedgeworth\Flow\Action\ActionHooks;
use Bga\Games\projectedgeworth\Flow\Action\ActionPhases;
use Bga\Games\projectedgeworth\Flow\Action\ActionServices;

/**
 * 状態機械のドライバが共有する部品（DB・グローバル値・ルール層・通知の組み立て・窓の問い合わせ）。BGA の Table に依存しない。
 * Game が 1 つ作って状態クラスに配る。テストは PDO の Database と ArrayGlobals で作る。
 */
final class FlowContext
{
    public readonly ActionPhases $phases;
    public readonly ActionServices $services;
    public readonly Notices $notices;

    /**
     * @param \Closure(int): string $nameOf プレイヤー ID => プレイヤー名
     * @param ActionHooks|null $hooks カード効果の問い合わせ口。効果エンジンの結線（N-4・N-7）までは null（効果なし）
     */
    public function __construct(
        public readonly Database $db,
        public readonly GlobalValues $globals,
        public readonly RandomSource $random,
        \Closure $nameOf,
        public readonly WindowGate $gate = new NoEffectWindowGate(),
        ?ActionHooks $hooks = null,
    ) {
        $this->phases = ActionPhases::load($db, $globals, $random, $hooks);
        $this->services = $this->phases->services();
        $this->notices = new Notices($nameOf);
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
