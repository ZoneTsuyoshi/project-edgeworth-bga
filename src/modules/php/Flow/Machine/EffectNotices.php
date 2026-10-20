<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Data\HeldNotificationRepository;
use Bga\Games\projectedgeworth\Data\StagedLinkRepository;
use Bga\Games\projectedgeworth\Effects\LogEntry;
use Bga\Games\projectedgeworth\Material\ProjectCards;
use Bga\Games\projectedgeworth\Material\SatelliteCards;

/**
 * 窓の中の解決の結果の通知（010 §10.5）。効果エンジンのログ（N27・N28・N22）と、`DbGame` の書き込みの記録（N8・N10・N11・N12・N14・N15・N17・
 * N19・N29）から組み立てる。
 *
 * - `build()`: 全員に見える通知（保留）と、本人だけの通知（N9 の手札の ID、N22）を分けて返す。
 * - `hold()`: 保留の通知を `held_notif` に積み、本人への先送り（メッセージ空、`ev_id` 付き）を返す。
 * - `release()`: バリアで保留を全員へ（基準プレイヤーから席順でまとめる）。通知を返し、`held_notif` を空にする。
 * 圧縮（010 §10.5 の 5）はしない（V-6 の実測のあとで足す）。
 */
final class EffectNotices
{
    private readonly HeldNotificationRepository $held;

    public function __construct(private readonly FlowContext $ctx)
    {
        $this->held = new HeldNotificationRepository($ctx->db);
    }

    /**
     * @param list<LogEntry> $logs
     * @param list<array<string, mixed>> $journal
     * @return array{held: list<Notice>, private: list<Notice>}
     */
    public function build(int $origin, array $logs, array $journal): array
    {
        $n = $this->ctx->notices;
        $services = $this->ctx->services;
        $held = [];
        $private = [];

        foreach ($logs as $log) {
            $a = $log->args;
            if ($log->key === 'effectUsed') {
                $hostKey = (string) $a['host'];
                $held[] = $n->effectUsed((int) $a['player_id'], $this->sourceName($hostKey), $this->hostSatellite($hostKey), (bool) $a['mark_used']);
            } elseif ($log->key === 'linkStaged') {
                $staged = new StagedLinkRepository($this->ctx->db);
                $playerId = (int) $a['player_id'];
                $private[] = $n->linkStaged(
                    $playerId,
                    count($staged->forPlayer($playerId)),
                    (int) $a['cell'],
                    (int) $a['sat_id'],
                    (bool) $a['special'],
                    (int) $a['cost'],
                    $staged->availableMaya($playerId),
                    $staged->remainingCubes($playerId),
                );
            }
        }

        $touched = [];
        foreach ($journal as $e) {
            switch ($e['t']) {
                case 'draw':
                    if ($e['reshuffled']) {
                        $held[] = $n->deckReshuffled($this->ctx->deckCount());
                    }
                    foreach ($n->cardsDrawn((int) $e['p'], $e['cards'], 'hand', 'effect', $this->ctx->deckCount(), $this->ctx->discardCount()) as $notice) {
                        if ($notice->to === null) {
                            $held[] = $notice;
                        } else {
                            $private[] = $notice;
                        }
                    }
                    break;
                case 'discard':
                    $held[] = $n->cardsDiscarded((int) $e['p'], $e['cards'], (string) $e['reason'], $this->ctx->discardCount());
                    break;
                case 'maya':
                    $held[] = $n->mayaChanged((int) $e['p'], (int) $e['delta'], (int) $e['maya'], (string) $e['reason']);
                    break;
                case 'budget':
                    $held[] = $n->budgetChanged((int) $e['p'], (int) $e['delta'], (int) $e['budget'], 'effect');
                    break;
                case 'deploy':
                    if (($e['reshuffled'] ?? false) === true) {
                        $held[] = $n->deckReshuffled($this->ctx->deckCount());
                    }
                    $sat = $services->satellites->get((int) $e['sat']);
                    $held[] = $n->cardDeployed((int) $e['p'], (int) $e['card'], $sat, (int) $e['tier'], $services->fuelCapacity($sat->id), 0, (bool) $e['hand']);
                    break;
                case 'turn':
                    $held[] = $n->satelliteTurned((int) $e['p'], $services->satellites->get((int) $e['sat']), (string) $e['dir'], (bool) $e['same']);
                    break;
                case 'fuel':
                    $held[] = $n->fuelChanged($services->satellites->get((int) $e['sat']), (int) $e['delta'], (int) $e['used'], (int) $e['capacity'], 'effect');
                    break;
                case 'actionKept':
                    // P001・P002: 縦向きのアクションカードを手札に戻した（N5 の回収は横向きと伝えているので、手札へ直す）
                    $held[] = $n->actionCardsCollected([(int) $e['p'] => ['toHand' => [(int) $e['action']], 'toSideways' => []]]);
                    break;
                case 'sat':
                    $touched[(int) $e['sat']] = true;
                    break;
                default:
                    break;
            }
        }
        foreach (array_keys($touched) as $satId) {
            $sat = $services->satellites->get($satId);
            $held[] = $n->satelliteChanged($sat, $services->cards->deployedIds($satId));
        }

        return ['held' => array_values(array_filter($held, static fn(?Notice $x): bool => $x !== null)), 'private' => $private];
    }

    /**
     * N27: ステップの選択に同梱した起動の修正を使った（コストを払うコミットの時点）。
     */
    public function used(int $playerId, string $hostKey): Notice
    {
        return $this->ctx->notices->effectUsed($playerId, $this->sourceName($hostKey), $this->hostSatellite($hostKey), true);
    }

    /**
     * N28: [Skip]。
     */
    public function skipped(int $playerId, string $cardKey, string $hostKey): Notice
    {
        return $this->ctx->notices->effectSkipped($playerId, $this->sourceName($hostKey));
    }

    /**
     * 保留に積み、本人への先送り（メッセージ空・`ev_id` 付き）を返す。
     *
     * @param list<Notice> $notices
     * @return list<Notice>
     */
    public function hold(int $origin, array $notices): array
    {
        $early = [];
        foreach ($notices as $notice) {
            $from = (int) ($notice->args['player_id'] ?? $origin);
            $evId = $this->ctx->globals->incHeldSeq();
            $args = $notice->args + ['ev_id' => $evId];
            $this->held->push($from, $notice->name, Notices::heldVariant($notice), $args);
            if ($from > 0) {
                $early[] = new Notice($notice->name, '', $args, $from);
            }
        }

        return $early;
    }

    /**
     * バリア: 保留を全員へ流す（基準プレイヤーから時計回りの席順でプレイヤーごとにまとめ、同じプレイヤーの中は時系列）。
     * 保留は空にする。流した `ev_id` は `flow_ctx` に溜め、窓を閉じるときの N26 に載せる（`closed()`）。
     *
     * @return list<Notice>
     */
    public function release(): array
    {
        $order = $this->ctx->services->players->playerIds();
        $base = array_search($this->ctx->globals->basePlayer(), $order, true);
        if ($base !== false && $base > 0) {
            $order = [...array_slice($order, $base), ...array_slice($order, 0, $base)];
        }
        $rank = array_flip($order);
        $items = $this->held->all();
        usort($items, static fn($a, $b): int => [$rank[$a->originPlayerId] ?? 99, $a->id] <=> [$rank[$b->originPlayerId] ?? 99, $b->id]);

        $notices = [];
        $evIds = [];
        foreach ($items as $item) {
            $evIds[] = (int) ($item->args['ev_id'] ?? 0);
            $notices[] = new Notice(
                $item->type,
                Notices::heldMessage($item->type, $item->variant),
                $item->args + ['early' => true],
            );
        }
        $this->held->clear();
        $context = $this->ctx->globals->flowContext();
        $context['evIds'] = array_merge((array) ($context['evIds'] ?? []), $evIds);
        $this->ctx->globals->setFlowContext($context);

        return $notices;
    }

    /**
     * N26: 窓を閉じた（保留を流した後）。溜めた `ev_id` を載せて空にする。
     */
    public function closed(string $windowId): Notice
    {
        $context = $this->ctx->globals->flowContext();
        $evIds = array_values(array_map('intval', (array) ($context['evIds'] ?? [])));
        unset($context['evIds']);
        $this->ctx->globals->setFlowContext($context);

        return $this->ctx->notices->windowClosed($windowId, $evIds);
    }

    private function sourceName(string $hostKey): string
    {
        [$kind, $src] = explode(':', $hostKey) + [1 => '0'];
        if ($kind === 'P') {
            return ProjectCards::get(sprintf('P%03d', (int) $src))->nameEn;
        }
        $sat = $this->ctx->services->satellites->get((int) $src);
        $card = SatelliteCards::get($sat->cardId);

        return ucfirst(strtolower($card->plan->value)) . ' ' . ucfirst(strtolower($card->shape->value));
    }

    private function hostSatellite(string $hostKey): int
    {
        [$kind, $src] = explode(':', $hostKey) + [1 => '0'];
        if ($kind === 'S') {
            return (int) $src;
        }
        $at = $this->ctx->services->cards->locationOf((int) $src);

        return $at['arg'];
    }
}
