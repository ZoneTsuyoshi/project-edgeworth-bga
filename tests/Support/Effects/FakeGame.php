<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support\Effects;

use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameMutator;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameView;
use Bga\Games\ProjectEdgeworth\Flow\FuelMath;
use Bga\Games\ProjectEdgeworth\Material\ProjectCards;
use Bga\Games\ProjectEdgeworth\Material\ProjectType;
use Bga\Games\ProjectEdgeworth\Material\SatelliteCards;
use Bga\Games\ProjectEdgeworth\Material\SatelliteShape;

/**
 * 011 §11-1 の `FakeGame`: `GameView` / `GameMutator` のメモリ実装。衛星・手札・マヤ・山札を組み立てて、効果エンジンを PHPUnit だけで試す。
 * 山札の順序は固定（先頭から引く）。
 */
final class FakeGame implements GameView, GameMutator
{
    public const SELL_PRICE = 3;

    /** @var list<int> */
    private array $players = [];

    /** @var array<int, int> */
    public array $maya = [];

    /** @var array<int, int> */
    public array $budget = [];

    /** @var array<int, list<int>> プレイヤー => 手札のカード番号 */
    public array $hand = [];

    /** @var list<int> 山札（先頭から引く） */
    public array $deck = [];

    /** @var array<int, array{player: int, shape: SatelliteShape, active: bool, key: string}> */
    private array $satellites = [];

    /** @var list<array{no: int, sat: int}> 配備順 */
    private array $deployed = [];

    /** @var list<int> 捨て札（山札が空になったら、この順のまま山札にする。乱数は固定） */
    public array $discard = [];

    /** @var array<int, int> 衛星 ID => 消費した燃料 c（001 §4-4） */
    public array $fuelUsed = [];

    /** @var array<int, list<int>> プレイヤー => 縦向きのアクションカード（1〜5） */
    public array $upright = [];

    /** @var array<int, list<int>> プレイヤー => 計画フェイズの回収で手札に戻したアクションカード */
    public array $keptActions = [];

    /** @var array<int, list<int>> プレイヤー => 通信確立済みの区域 */
    public array $linkedCells = [];

    public bool $proposalPhase = false;

    /** @var array<string, true> */
    public array $used = [];

    private int $nextSat = 1;

    /** @var list<array{string, int}> 効果が行った操作の記録 [名前, 値] */
    public array $journal = [];

    public int $sellPrice = self::SELL_PRICE;

    /**
     * @param list<int> $hand
     */
    public function player(int $id, int $maya = 0, array $hand = []): self
    {
        $this->players[] = $id;
        $this->maya[$id] = $maya;
        $this->budget[$id] = 0;
        $this->hand[$id] = $hand;

        return $this;
    }

    /**
     * 衛星を足して ID を返す。`cardKey` は衛星カードの name_id（省略時は効果の定義のない名前。MOON 面の効果 R001〜R005 を意図せず働かせないため）。
     */
    public function satellite(int $player, SatelliteShape $shape, bool $active = true, ?string $cardKey = null): int
    {
        $id = $this->nextSat++;
        $this->satellites[$id] = [
            'player' => $player,
            'shape' => $shape,
            'active' => $active,
            'key' => $cardKey ?? strtolower($shape->value) . '_plain',
        ];

        return $id;
    }

    public function setActive(int $satId, bool $active): void
    {
        $this->satellites[$satId]['active'] = $active;
    }

    /** プロジェクトカード（'P013' など）を衛星に配備する（下の段から順に積む） */
    public function deploy(int $satId, string $cardId): self
    {
        $this->deployed[] = ['no' => (int) substr($cardId, 1), 'sat' => $satId];

        return $this;
    }

    public function cardsOn(int $satId): int
    {
        return count(array_filter($this->deployed, static fn(array $d): bool => $d['sat'] === $satId));
    }

    public function playerIds(): array
    {
        return $this->players;
    }

    public function hosts(int $playerId): array
    {
        $hosts = [];
        foreach ($this->satellites as $satId => $sat) {
            if ($sat['player'] !== $playerId) {
                continue;
            }
            $hosts[] = $this->satHost($satId);
            $order = 0;
            foreach ($this->deployed as $d) {
                if ($d['sat'] === $satId) {
                    $hosts[] = $this->cardHost($d['no'], $satId, ++$order);
                }
            }
        }

        return $hosts;
    }

    public function host(string $kind, int $src): ?EffectHost
    {
        if ($kind === 'S') {
            return isset($this->satellites[$src]) ? $this->satHost($src) : null;
        }
        $order = [];
        foreach ($this->deployed as $d) {
            $order[$d['sat']] = ($order[$d['sat']] ?? 0) + 1;
            if ($d['no'] === $src) {
                return $this->cardHost($src, $d['sat'], $order[$d['sat']]);
            }
        }

        return null;
    }

    public function maya(int $playerId): int
    {
        return $this->maya[$playerId];
    }

    public function hand(int $playerId): array
    {
        return $this->hand[$playerId];
    }

    public function sellPrice(int $playerId): int
    {
        return $this->sellPrice;
    }

    public function markUsed(EffectHost $host): void
    {
        $this->used[$host->key()] = true;
    }

    public function payMaya(int $playerId, int $amount): void
    {
        $this->maya[$playerId] -= $amount;
    }

    public function sellCards(int $playerId, array $cardIds): void
    {
        $this->hand[$playerId] = array_values(array_diff($this->hand[$playerId], $cardIds));
        $this->maya[$playerId] += count($cardIds) * $this->sellPrice;
    }

    // ---- 効果の部品（テスト用）が使う書き込み ----

    public function draw(int $playerId, int $count): int
    {
        $drawn = 0;
        for ($i = 0; $i < $count; $i++) {
            if ($this->deck === []) {
                $this->deck = $this->discard;
                $this->discard = [];
            }
            if ($this->deck === []) {
                break;
            }
            $this->hand[$playerId][] = array_shift($this->deck);
            $drawn++;
        }

        return $drawn;
    }

    public function drawCards(int $playerId, int $count): int
    {
        $drawn = $this->draw($playerId, $count);
        $this->note('drawCards', $drawn);

        return $drawn;
    }

    public function gainMaya(int $playerId, int $amount): void
    {
        $this->maya[$playerId] += $amount;
        $this->note('gainMaya', $amount);
    }

    public function changeBudget(int $playerId, int $amount): void
    {
        $this->budget[$playerId] += $amount;
        $this->note('changeBudget', $amount);
    }

    public function recoverFuel(int $satelliteId, int $amount): int
    {
        $before = $this->fuelUsed[$satelliteId] ?? 0;
        $after = FuelMath::change($before, -$amount, $this->fuelCapacity($satelliteId));
        $this->fuelUsed[$satelliteId] = $after;
        $this->note('recoverFuel', $before - $after);

        return $before - $after;
    }

    public function returnPlottedActionToHand(int $playerId, int $actionNo): void
    {
        $this->upright[$playerId] = array_values(array_diff($this->upright[$playerId] ?? [], [$actionNo]));
        $this->keptActions[$playerId][] = $actionNo;
        $this->note('keepAction', $actionNo);
    }

    public function isProposalPhase(): bool
    {
        return $this->proposalPhase;
    }

    public function isOwnLinkedArea(int $playerId, int $cell): bool
    {
        return in_array($cell, $this->linkedCells[$playerId] ?? [], true);
    }

    /** 燃料庫の数 C = 衛星カード + 配備されているプロジェクトカード（001 §4-4） */
    public function fuelCapacity(int $satId): int
    {
        $key = $this->satellites[$satId]['key'];
        $sum = 0;
        foreach (SatelliteCards::all() as $card) {
            if ($card->nameId === $key) {
                $sum = $card->fuelSlots;
                break;
            }
        }
        foreach ($this->deployed as $d) {
            if ($d['sat'] === $satId) {
                $sum += ProjectCards::get(sprintf('P%03d', $d['no']))->fuelSlots;
            }
        }

        return $sum;
    }

    public function note(string $name, int $value = 0): void
    {
        $this->journal[] = [$name, $value];
    }

    /** @return list<string> */
    public function journalNames(): array
    {
        return array_map(static fn(array $j): string => $j[0], $this->journal);
    }

    public function deviceCountOn(int $satelliteId): int
    {
        $count = 0;
        foreach ($this->deployed as $d) {
            if ($d['sat'] === $satelliteId && ProjectCards::get(sprintf('P%03d', $d['no']))->type === ProjectType::Device) {
                $count++;
            }
        }

        return $count;
    }

    public function removeFromHand(int $playerId, int $cardNo): void
    {
        $this->hand[$playerId] = array_values(array_diff($this->hand[$playerId], [$cardNo]));
    }

    private function satHost(int $satId): EffectHost
    {
        $s = $this->satellites[$satId];
        $key = 'S:' . $satId;

        return new EffectHost('S', $satId, $s['key'], $s['player'], $satId, $s['shape'], 0, $s['active'], isset($this->used[$key]));
    }

    private function cardHost(int $no, int $satId, int $order): EffectHost
    {
        $s = $this->satellites[$satId];
        $key = 'P:' . $no;

        return new EffectHost(
            'P',
            $no,
            ProjectCards::get(sprintf('P%03d', $no))->nameId,
            $s['player'],
            $satId,
            $s['shape'],
            $order,
            $s['active'],
            isset($this->used[$key]),
        );
    }
}
