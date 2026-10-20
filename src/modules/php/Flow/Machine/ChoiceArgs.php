<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Flow\Action\Payability;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Flow\StateId;

/**
 * 同時選択の状態の getArgs（010 §13）。公開部分は「参加者」と「確定済みのプレイヤー ID」だけ（選択内容を入れない）。
 * 本人の確定内容と候補は `_private`（参加者全員分。確定済みで待機中の人にも返す。再読み込みで復元できる）。DB は読むだけ。
 * 候補の金額はサーバーが計算した値で、クライアントは計算しない（008 §3-6）。
 */
final class ChoiceArgs
{
    public function __construct(private readonly FlowContext $ctx)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(ChoiceFlow $flow): array
    {
        $services = $this->ctx->services;
        $participants = $services->globals->stepParticipants();
        $args = [
            'participants' => $participants,
            'confirmed' => $services->choices->confirmedPlayers($flow->stateId),
        ];
        if ($flow->stateId === StateId::PROPOSAL_KEEP_CHOOSE) {
            // 引いた枚数だけが公開情報。残す枚数も公開してよい
            $args['keepCount'] = [];
            foreach ($participants as $playerId) {
                $args['keepCount'][$playerId] = $this->ctx->phases->proposal()->keepCount($playerId);
            }
        }
        $private = [];
        foreach ($participants as $playerId) {
            $private[$playerId] = ['myChoice' => $services->choices->ownChoice($playerId, $flow->stateId)]
                + $this->detail($flow, $playerId);
        }
        $args['_private'] = $private;
        return $args;
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(ChoiceFlow $flow, int $playerId): array
    {
        $services = $this->ctx->services;
        $hand = $services->cards->idsIn(CardLocation::Hand, $playerId);
        $sell = ['hand' => $hand, 'sellValue' => $services->selling->price($playerId)];
        $phases = $this->ctx->phases;
        return match ($flow->stateId) {
            StateId::SETUP_MULLIGAN => ['hand' => $hand],
            StateId::PLANNING_PLOT => ['selectable' => $phases->planning()->selectable($playerId)],
            StateId::DEPLOY_MAIN_CHOOSE, StateId::DEPLOY_BONUS_CHOOSE => $sell + $this->deploy($playerId, $flow->mode),
            StateId::LAUNCH_MAIN_CHOOSE, StateId::LAUNCH_BONUS_CHOOSE => $sell + $this->launch($playerId, $flow->mode),
            StateId::LINK_MAIN_CHOOSE, StateId::LINK_BONUS_CHOOSE => $sell + $this->link($playerId, $flow->mode),
            StateId::PROPOSAL_KEEP_CHOOSE => [
                'drawn' => $phases->proposal()->drawn($playerId),
                'keep' => $phases->proposal()->keepCount($playerId),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function deploy(int $playerId, StepMode $mode): array
    {
        $deploy = $this->ctx->phases->deploy();
        $targets = [];
        foreach ($deploy->hand($playerId) as $cardNumber) {
            foreach ($deploy->targets($playerId, $cardNumber) as $target) {
                $targets[$cardNumber][] = [
                    'satId' => $target->satellite->id,
                    'tier' => $target->deployedCount + 1,
                    'cost' => $target->cost->total(),
                    'blocked' => $target->blocked,
                    'minSales' => $target->payability->minSales(),
                    'selectable' => $target->isSelectable(),
                ];
            }
        }
        $args = ['targets' => $targets];
        if ($mode === StepMode::Bonus) {
            $args['canDraw'] = $this->ctx->deckCount() + $this->ctx->discardCount() > 0;
        }
        return $args;
    }

    /**
     * @return array<string, mixed>
     */
    private function launch(int $playerId, StepMode $mode): array
    {
        $launch = $this->ctx->phases->launch();
        $launchable = [];
        if ($mode === StepMode::Main) {
            foreach ($launch->launchOptions($playerId) as $option) {
                $launchable[] = [
                    'satId' => $option->satellite->id,
                    'cost' => $option->cost,
                    'blocked' => $option->blocked,
                    'minSales' => $option->payability->minSales(),
                    'selectable' => $option->isSelectable(),
                ];
            }
        }
        $turnable = [];
        foreach ($launch->turnOptions($playerId) as $option) {
            $turnable[] = [
                'satId' => $option->satellite->id,
                'dir' => $option->satellite->direction?->value,
                'blocked' => $option->blocked,
                'selectable' => $option->isSelectable(),
            ];
        }
        $args = ['launchable' => $launchable, 'turnable' => $turnable];
        if ($mode === StepMode::Bonus) {
            $args['canBudget'] = $launch->canRaiseBudget($playerId);
        }
        return $args;
    }

    /**
     * @return array<string, mixed>
     */
    private function link(int $playerId, StepMode $mode): array
    {
        $linkable = [];
        foreach ($this->ctx->phases->link()->candidates($playerId, $mode) as $candidate) {
            $sats = [];
            foreach ($candidate->options as $option) {
                $sats[] = [
                    'satId' => $option->satellite->id,
                    'cost' => [
                        'area' => $option->cost->areaCost + $option->cost->specialCost,
                        'pioneer' => $option->cost->pioneer,
                        'total' => $option->cost->total(),
                    ],
                    'minSales' => self::minSales($option->payability),
                ];
            }
            $linkable[] = ['cell' => $candidate->cell, 'selectable' => $candidate->isSelectable(), 'sats' => $sats];
        }
        return ['linkable' => $linkable];
    }

    private static function minSales(Payability $payability): ?int
    {
        return $payability->minSales();
    }
}
