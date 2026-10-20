<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Flow\Action\LinkCommit;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Flow\Action\StepOutcome;
use Bga\Games\projectedgeworth\Flow\Action\StepResult;

/**
 * 一斉コミット（II・III・IV のメイン / ボーナス、V の残すカード、計画の公開）の結果を、010 §10.2 の通知にする。
 * 通知の順は処理の順（コストの支払い → 配備・打ち上げなど → 区域ボーナス）。最後に N33（枚数の再同期）を付ける。
 */
final class OutcomeNotices
{
    public function __construct(private readonly FlowContext $ctx)
    {
    }

    /**
     * 計画の公開（N6）。席順（基準プレイヤーから）に 1 件ずつ。
     *
     * @return list<Notice>
     */
    public function reveal(StepResult $result): array
    {
        $notices = [];
        foreach ($result->outcomes as $outcome) {
            $notices[] = $this->ctx->notices->actionRevealed($outcome->playerId, (int) $outcome->details['action']);
        }
        return $notices;
    }

    /**
     * @param int $actionNo 2〜5
     * @param list<int> $autoPlayers 入場時に自動で [Pass] 相当を確定したプレイヤー（入場時に N24 を出したので、ここでは出さない）
     * @param array<int, int> $mayaBefore コミット前の所持マヤ
     * @return list<Notice>
     */
    public function commit(int $actionNo, StepMode $mode, StepResult $result, array $autoPlayers, array $mayaBefore, int $reshuffleMark): array
    {
        $tracker = new MayaTracker($this->ctx->notices, $mayaBefore);
        $notices = $this->ctx->reshuffleNotices($reshuffleMark);

        if ($actionNo === 4 && isset($result->extra['commit']) && $result->extra['commit'] instanceof LinkCommit) {
            $notices = array_merge($notices, $this->linkNotices($result, $result->extra['commit'], $autoPlayers, $tracker));
        } else {
            foreach ($result->outcomes as $outcome) {
                $notices = array_merge($notices, $this->outcome($actionNo, $mode, $outcome, $autoPlayers, $tracker));
            }
        }
        $notices[] = $this->ctx->counters();
        return array_values(array_filter($notices));
    }

    /**
     * 状態 32 のバリアで、窓のステージを一斉にコミットした結果の通知（支払い、先駆コスト、キューブ、区域ボーナス）。
     * コストを払えず取りやめた依頼は N24（申し送り N-10）。
     *
     * @param array<int, int> $mayaBefore コミット前の所持マヤ
     * @return list<Notice>
     */
    public function barrierCommit(LinkCommit $commit, array $mayaBefore): array
    {
        $tracker = new MayaTracker($this->ctx->notices, $mayaBefore);
        $notices = [];
        foreach ($commit->failed as $request) {
            $notices[] = $this->ctx->notices->actionPassed($request->playerId, 'link', false);
        }
        $notices = array_merge($notices, $this->linkNotices(new StepResult([]), $commit, [], $tracker));
        return array_values(array_filter($notices));
    }

    /**
     * 入場時に自動で [Pass] 相当にしたプレイヤーの N24。
     *
     * @param list<int> $autoPlayers
     * @return list<Notice>
     */
    public function autoPassed(int $actionNo, StepMode $mode, array $autoPlayers): array
    {
        $kind = self::passKind($actionNo, $mode);
        return array_map(fn(int $id): Notice => $this->ctx->notices->actionPassed($id, $kind, true), $autoPlayers);
    }

    public static function passKind(int $actionNo, StepMode $mode): string
    {
        if ($actionNo === 5) {
            return 'keep';
        }
        if ($mode === StepMode::Bonus && $actionNo !== 4) {
            return 'bonus';
        }
        return match ($actionNo) {
            2 => 'deploy',
            3 => 'launch',
            default => 'link',
        };
    }

    /**
     * @param list<int> $autoPlayers
     * @return list<Notice|null>
     */
    private function outcome(int $actionNo, StepMode $mode, StepOutcome $outcome, array $autoPlayers, MayaTracker $tracker): array
    {
        $n = $this->ctx->notices;
        $services = $this->ctx->services;
        $pid = $outcome->playerId;
        $d = $outcome->details;

        if ($outcome->isPass()) {
            return in_array($pid, $autoPlayers, true) ? [] : [$n->actionPassed($pid, self::passKind($actionNo, $mode), false)];
        }

        switch ($outcome->op) {
            case 'deploy':
                $sat = $services->satellites->get((int) $d['sat']);
                return array_merge(
                    $this->selling($pid, $d['sold'], $tracker),
                    [
                        $tracker->change($pid, -(int) $d['cost'], 'cost'),
                        $n->cardDeployed(
                            $pid,
                            (int) $d['card'],
                            $sat,
                            (int) $d['tier'],
                            $services->fuelCapacity($sat->id),
                            (int) $d['cost'],
                            true,
                        ),
                    ],
                );
            case 'draw':
                return $this->draw($pid, $d['cards'], 'hand', 'bonus');
            case 'launch':
                $sat = $services->satellites->get((int) $d['sat']);
                return array_merge(
                    $this->selling($pid, $d['sold'], $tracker),
                    [
                        $tracker->change($pid, -(int) $d['cost'], 'cost'),
                        $n->satelliteLaunched($pid, $sat, (int) $d['cell'], (string) $d['dir'], (int) $d['cost']),
                    ],
                );
            case 'turn':
                $sat = $services->satellites->get((int) $d['sat']);
                return [$n->satelliteTurned($pid, $sat, (string) $d['dir'], (bool) $d['same'])];
            case 'budget':
                return [$n->budgetChanged($pid, 1, (int) $d['budget'], 'bonus')];
            case 'keep':
                $discard = array_values(array_map('intval', $d['discarded']));
                $notices = [];
                if ($discard !== []) {
                    $notices[] = $n->cardsDiscarded($pid, $discard, 'proposal', $this->ctx->discardCount());
                }
                $notices[] = $n->drawnCardsKept($pid, array_values(array_map('intval', $d['kept'])));
                return $notices;
            default:
                return [];
        }
    }

    /**
     * @param list<int> $autoPlayers
     * @return list<Notice|null>
     */
    private function linkNotices(StepResult $result, LinkCommit $commit, array $autoPlayers, MayaTracker $tracker): array
    {
        $n = $this->ctx->notices;
        $services = $this->ctx->services;
        $notices = [];

        // 通信確立しなかった人（[Pass] = キューブの放棄、払えず取りやめ）
        foreach ($result->outcomes as $outcome) {
            if ($outcome->isPass() && !in_array($outcome->playerId, $autoPlayers, true)) {
                $notices[] = $n->actionPassed($outcome->playerId, 'link', false);
            }
        }

        // 支払い（売却 → 区域コスト + 特殊コスト）と、先駆コストの受け渡し（席順）
        foreach ($commit->entries as $entry) {
            $pid = $entry->request->playerId;
            $notices = array_merge($notices, $this->selling($pid, $entry->payment->soldCards, $tracker));
            $notices[] = $tracker->change($pid, -($entry->request->cost->areaCost + $entry->request->cost->specialCost), 'cost');
        }
        foreach ($commit->entries as $entry) {
            $from = $entry->request->playerId;
            foreach ($entry->request->cost->pioneer as $share) {
                $tracker->change($from, -$share['amount'], 'pioneer');
                $tracker->change($share['to'], $share['amount'], 'pioneer');
                $notices[] = $n->mayaTransferred($from, $share['to'], $share['amount'], $tracker->current($from), $tracker->current($share['to']));
            }
        }

        // キューブの設置と区域ボーナス
        foreach ($commit->entries as $entry) {
            $pid = $entry->request->playerId;
            $cell = $entry->request->cell;
            $label = Notices::areaLabel($services->layout->areaType($cell)->value);
            $cubesLeft = 16 - $services->links->countFor($pid);
            $notices[] = $n->linkEstablished(
                $pid,
                $cell,
                $label,
                $entry->link->slot,
                $entry->link->level,
                $entry->request->satelliteId,
                $entry->request->special,
                (int) $commit->commitNo,
                $cubesLeft,
            );
            $bonus = $entry->bonus;
            $notices[] = $tracker->change($pid, $bonus->maya, 'areaBonus');
            $notices = array_merge($notices, $this->draw($pid, $bonus->drawn, 'hand', 'areaBonus'));
            if ($bonus->fuelRecovered > 0) {
                $sat = $services->satellites->get($entry->request->satelliteId);
                $notices[] = $n->fuelChanged(
                    $sat,
                    -$bonus->fuelRecovered,
                    $sat->fuelUsed,
                    $services->fuelCapacity($sat->id),
                    'deepSea',
                );
            }
        }
        return $notices;
    }

    /**
     * @param array<mixed> $sold
     * @return list<Notice|null>
     */
    private function selling(int $playerId, array $sold, MayaTracker $tracker): array
    {
        if ($sold === []) {
            return [];
        }
        $price = $this->ctx->services->selling->price($playerId);
        return [
            $this->ctx->notices->cardsDiscarded($playerId, array_values(array_map('intval', $sold)), 'sell', $this->ctx->discardCount()),
            $tracker->change($playerId, $price * count($sold), 'sell'),
        ];
    }

    /**
     * @param list<int> $cards
     * @return list<Notice>
     */
    private function draw(int $playerId, array $cards, string $zone, string $reason): array
    {
        return $this->ctx->notices->cardsDrawn($playerId, $cards, $zone, $reason, $this->ctx->deckCount(), $this->ctx->discardCount());
    }
}
