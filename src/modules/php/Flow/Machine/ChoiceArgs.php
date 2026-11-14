<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Effects\Query\ProposalOption;
use Bga\Games\projectedgeworth\Effects\Runtime\StepMods;
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
        // BGA は getArgs を onEnteringState の「前」に呼ぶ（State classes の仕様）。入場前は enter() がまだ参加者も自動確定も
        // 書いていないので、step_participants が空のあいだは enter() と同じ規則で求める（DB は書かない）。
        // これを怠ると、入場直後の args の参加者と _private が空になり、全員の画面が「Waiting for other players」で止まる。
        $participants = $services->globals->stepParticipants();
        $confirmed = $services->choices->confirmedPlayers($flow->stateId);
        /** @var array<int, array<mixed>> $pendingAuto 入場前に、enter() が自動で確定するはずの人の [Pass] 相当 */
        $pendingAuto = [];
        if ($participants === []) {
            $participants = $flow->step->participants($flow->mode);
            foreach ($participants as $playerId) {
                if (!$flow->step->canAct($playerId, $flow->mode) && !in_array($playerId, $confirmed, true)) {
                    $confirmed[] = $playerId;
                    $pendingAuto[$playerId] = $flow->step->defaultChoice($playerId, $flow->mode);
                }
            }
            sort($confirmed);
        }
        $args = [
            'participants' => $participants,
            'confirmed' => $confirmed,
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
            $private[$playerId] = $this->privateFor($flow, $playerId, $pendingAuto[$playerId] ?? null);
        }
        $args['_private'] = $private;
        return $args;
    }

    /**
     * 1 人分の `_private`（本人の確定内容と候補）。II 配備のステージ・[Reset] の通知にも同じ形で載せる。
     * `$autoChoice` は入場前の getArgs で、まだ書かれていない自動 [Pass] を確定内容として返すための値（入場後の args と同じ形にする）。
     *
     * @param array<mixed>|null $autoChoice
     * @return array<string, mixed>
     */
    public function privateFor(ChoiceFlow $flow, int $playerId, ?array $autoChoice = null): array
    {
        $private = ['myChoice' => $this->ctx->services->choices->ownChoice($playerId, $flow->stateId) ?? $autoChoice]
            + $this->detail($flow, $playerId);
        // 入場時に自動で確定される人（選ぶ余地がない）の印。クライアントは [Change my choice] を出さない（011 追補 2026-11-14）
        if ($flow->stateId === StateId::PROPOSAL_KEEP_CHOOSE && !$flow->step->canAct($playerId, $flow->mode)) {
            $private['auto'] = true;
        }
        return $private;
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
            StateId::DEPLOY_MAIN_CHOOSE => $sell + $this->deploy($playerId),
            StateId::LAUNCH_MAIN_CHOOSE, StateId::LAUNCH_BONUS_CHOOSE => $sell + $this->launch($playerId, $flow->mode),
            StateId::LINK_MAIN_CHOOSE, StateId::LINK_BONUS_CHOOSE => $sell + $this->link($playerId, $flow->mode),
            StateId::PROPOSAL_KEEP_CHOOSE => $this->proposal($playerId, $hand) + ['sellValue' => $services->selling->price($playerId), 'hand' => $hand],
            default => [],
        };
    }

    /**
     * 企画（91）の候補。PROPOSAL_OPTIONS（P068・P070・P072）の候補は `extra`（なければ空。011 §6-5）。
     * 残す枚数 >= 引いた枚数のときは全部残すことが決まっているので、残す枚数を増やす P068・P072 を `extra` から除く（P070 だけ残す。011 追補 2026-11-14）。
     *
     * @param list<int> $hand
     * @return array<string, mixed>
     */
    private function proposal(int $playerId, array $hand): array
    {
        $proposal = $this->ctx->phases->proposal();
        $drawn = $proposal->drawn($playerId);
        $keep = $proposal->keepCount($playerId);
        $extra = $this->ctx->services->hooks->proposalExtras($playerId, $proposal->isBonusHolder($playerId), $drawn);
        if ($drawn !== [] && $keep >= count($drawn) && $extra !== []) {
            $options = array_values(array_filter(
                (array) ($extra['options'] ?? []),
                static fn(array $o): bool => $o['kind'] === ProposalOption::EXTRA_DISCARD,
            ));
            $extra = $options === [] ? [] : ['handDiscard' => false, 'handCandidates' => [], 'options' => $options];
        }

        return ['drawn' => $drawn, 'keep' => $keep, 'extra' => $extra];
    }

    /**
     * 配備の候補。ボーナス保持者（`bonus` = true）は、[Deploy another card] で 1 枚目を先に確定（ステージ）してから 2 枚目を選ぶ
     * （009 追補 2026-11-12）。`stage` は 0 = 未ステージ（S0）、1 = ステージ済み（S1）。`targets` は実際の盤面を見た候補で、
     * S1 ではステージ済みの 1 枚目が衛星に付いた状態の 2 枚目の候補（段・コスト・選べない理由・起動の修正）になる。
     * クライアントは見積もりを自前で計算しない。ステージ済みなら `stage1`（1 枚目の内容）、`canReset` / `resetBlockedReason` も載せる。
     *
     * @return array<string, mixed>
     */
    private function deploy(int $playerId): array
    {
        $deploy = $this->ctx->phases->deploy();
        $stage = $this->ctx->services->deployStages->find($playerId);
        $bonus = $deploy->isBonusHolder($playerId);
        $targets = [];
        foreach ($deploy->hand($playerId) as $cardNumber) {
            foreach ($deploy->targets($playerId, $cardNumber) as $target) {
                $entry = [
                    'satId' => $target->satellite->id,
                    'tier' => $target->deployedCount + 1,
                    'cost' => $target->cost->total(),
                    'blocked' => $target->blocked,
                    'minSales' => $target->payability->minSales(),
                    'selectable' => $target->isSelectable(),
                ];
                $entry += $this->mods(2, $playerId, ['card' => $cardNumber, 'sat' => $target->satellite->id], $target->cost->total());
                $targets[$cardNumber][] = $entry;
            }
        }
        $args = ['targets' => $targets, 'bonus' => $bonus, 'stage' => $stage === null ? 0 : 1, 'canReset' => false];
        if ($bonus) {
            $args['canDraw'] = $stage === null && $this->ctx->deckCount() + $this->ctx->discardCount() > 0;
        }
        if ($stage !== null) {
            $args['stage1'] = [
                'card' => $stage->card,
                'satId' => $stage->satId,
                'tier' => $stage->tier,
                'paid' => $stage->paid,
                'sold' => $stage->sold,
            ];
            $args['canReset'] = !$stage->irreversible;
            $args['resetBlockedReason'] = $stage->irreversible ? DeployStageFlow::REASON_IRREVERSIBLE : null;
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
                ] + $this->mods(3, $playerId, ['sat' => $option->satellite->id], $option->cost);
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
                ] + $this->mods(4, $playerId, ['sat' => $option->satellite->id, 'cell' => $candidate->cell], $option->cost->total());
            }
            $linkable[] = ['cell' => $candidate->cell, 'selectable' => $candidate->isSelectable(), 'sats' => $sats];
        }
        return ['linkable' => $linkable];
    }

    /**
     * 起動の修正（USE_MODS）の候補。使える修正がなければキーごと載せない（011 §6-3）。
     *
     * @param array<string, mixed> $subject
     * @return array{useMods?: array<string, mixed>}
     */
    private function mods(int $actionNo, int $playerId, array $subject, int $total): array
    {
        $options = $this->ctx->effects?->stepMods->options($actionNo, $playerId, $subject) ?? [];

        return $options === [] ? [] : ['useMods' => StepMods::describe($options, $total)];
    }

    private static function minSales(Payability $payability): ?int
    {
        return $payability->minSales();
    }
}
