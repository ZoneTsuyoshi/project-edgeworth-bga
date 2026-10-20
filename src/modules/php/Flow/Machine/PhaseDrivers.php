<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Effects\Runtime\ActionEvents;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Flow\StateId;

/**
 * アクションフェイズのドライバ（50 予算発行・60 配備・70 打ち上げ / 転回・80 通信確立・90 企画。009 §3.6・§3.7）。
 * 処理の並びは StepProgram の ops で表し、窓の位置（'win:XX'）は 009 §6.1 の表のとおり。
 * 効果の窓は WindowGate が必要と答えたときだけ開く（今回は素通り）。窓の差し込み口はすべて 'win:' の位置。
 */
final class PhaseDrivers
{
    public function __construct(private readonly FlowContext $ctx)
    {
    }

    /**
     * 50: I 予算発行。選択はない。ステップ 0: 窓 B0、1: 窓 XD、2: メインの収入（B1）、ボーナスの +6、窓 B2、3: 窓 B3。
     */
    public function budget(): DriverResult
    {
        $ops = ['win:B0', 'win:XD', 'gain', 'bonus', 'win:B2', 'win:B3', 'next'];
        return (new StepProgram($this->ctx, StateId::BUDGET_DRIVER))->run($ops, function (string $op, NoticeList $notes): ?string {
            $phase = $this->ctx->phases->budget();
            $players = $this->ctx->services->players;
            if ($op === 'gain') {
                foreach ($phase->applyMain() as $playerId => $gain) {
                    $notes->add($this->ctx->notices->mayaChanged($playerId, $gain, $players->maya($playerId), 'budget'));
                }
                $notes->add($this->ctx->counters());
            } elseif ($op === 'bonus') {
                $gains = $phase->applyBonus();
                foreach ($gains as $playerId => $gain) {
                    $notes->add($this->ctx->notices->mayaChanged($playerId, $gain, $players->maya($playerId), 'bonus'));
                }
            }
            return null;
        });
    }

    /**
     * 60・70・80: II 配備・III 打ち上げ / 転回・IV 通信確立（共通の構成）。
     * ステップ: 窓 XD（セットアップでは飛ばす）→ メインの選択 → メインのコミット → 窓 → ボーナスの選択 → ボーナスのコミット → 窓。
     *
     * @param int $actionNo 2・3・4
     */
    public function action(int $actionNo): DriverResult
    {
        [$driver, $mainState, $bonusState, $afterMain, $afterBonus] = match ($actionNo) {
            2 => [StateId::DEPLOY_DRIVER, StateId::DEPLOY_MAIN_CHOOSE, StateId::DEPLOY_BONUS_CHOOSE, ['win:DE'], ['win:DE']],
            3 => [StateId::LAUNCH_DRIVER, StateId::LAUNCH_MAIN_CHOOSE, StateId::LAUNCH_BONUS_CHOOSE, ['win:LE', 'win:TE'], ['win:TE']],
            4 => [StateId::LINK_DRIVER, StateId::LINK_MAIN_CHOOSE, StateId::LINK_BONUS_CHOOSE, ['win:KE'], ['win:KE']],
            default => throw new \InvalidArgumentException("action $actionNo is not a main/bonus action"),
        };
        $ops = array_merge(
            $this->ctx->globals->roundNo() === 0 ? [] : ['win:XD'],
            ['main', 'commit:main'],
            $afterMain,
            ['bonus', 'commit:bonus'],
            in_array($actionNo, [2, 3], true) ? ['win:AB'] : [],
            $afterBonus,
            ['next'],
        );
        $program = new StepProgram($this->ctx, $driver);
        return $program->run($ops, function (string $op, NoticeList $notes) use ($program, $ops, $actionNo, $mainState, $bonusState): ?string {
            switch ($op) {
                case 'main':
                    return 'main';
                case 'bonus':
                    if ($this->ctx->services->bonusHolders($actionNo) === []) {
                        $program->skipToEnd($ops);
                        return null;
                    }
                    return 'bonus';
                case 'commit:main':
                    $notes->addAll($this->commit($actionNo, StepMode::Main, $mainState));
                    return null;
                case 'commit:bonus':
                    $holders = $this->ctx->services->bonusHolders($actionNo);
                    $notes->addAll($this->commit($actionNo, StepMode::Bonus, $bonusState));
                    $this->ctx->record(...ActionEvents::bonusStep($actionNo, $holders));
                    return null;
                default:
                    throw new \LogicException("unknown op: $op");
            }
        });
    }

    /**
     * 90: V 企画。ステップ 0: 窓 XD、1: 全員が引く → 残すカードの選択（91）、2: 残す以外を捨てる、窓 PB。
     */
    public function proposal(): DriverResult
    {
        $ops = array_merge($this->ctx->globals->roundNo() === 0 ? [] : ['win:XD'], ['draw', 'win:PA', 'choose', 'commit', 'win:PB', 'next']);
        return (new StepProgram($this->ctx, StateId::PROPOSAL_DRIVER))->run($ops, function (string $op, NoticeList $notes): ?string {
            if ($op === 'draw') {
                $mark = $this->ctx->reshuffleMark();
                $drawn = $this->ctx->phases->proposal()->drawAll();
                $notes->addAll($this->ctx->reshuffleNotices($mark));
                foreach ($drawn as $playerId => $cards) {
                    $notes->addAll($this->ctx->notices->cardsDrawn(
                        $playerId,
                        $cards,
                        'drawn',
                        'proposal',
                        $this->ctx->deckCount(),
                        $this->ctx->discardCount(),
                    ));
                }
                $notes->add($this->ctx->counters());
                $this->ctx->record(...ActionEvents::proposalDrawn(array_map('count', $drawn)));
                return null;
            }
            if ($op === 'choose') {
                return 'choose';
            }
            $notes->addAll($this->commit(5, StepMode::Main, StateId::PROPOSAL_KEEP_CHOOSE));
            return null;
        });
    }

    /**
     * 選択状態の確定内容を一斉にコミットして、通知にする。
     *
     * @return list<Notice>
     */
    private function commit(int $actionNo, StepMode $mode, int $stateId): array
    {
        $services = $this->ctx->services;
        $participants = $services->globals->stepParticipants();
        $auto = array_values(array_filter(
            $participants,
            static fn(int $id): bool => $services->choices->isAuto($id, $stateId),
        ));
        $before = [];
        foreach ($services->players->playerIds() as $playerId) {
            $before[$playerId] = $services->players->maya($playerId);
        }
        $mark = $this->ctx->reshuffleMark();
        $step = $this->ctx->phases->stepFor($actionNo);

        // 起動の修正（USE_MODS）: 確定済みの鍵を、コストの計算の間だけ効かせ、払えた選択の分を使用済みにする
        $effects = $this->ctx->effects;
        $keysByPlayer = [];
        foreach ($effects === null ? [] : $participants as $playerId) {
            $own = $services->choices->ownChoice($playerId, $stateId);
            $keysByPlayer[$playerId] = array_values(array_map('strval', (array) ($own['useMods'] ?? [])));
        }
        $journalMark = $effects?->game->journalMark() ?? 0;
        $effects?->hooks->useMods(array_values(array_unique(array_merge([], ...array_values($keysByPlayer)))));
        try {
            $result = $this->ctx->phases->runner()->resolve($step, $stateId, $mode);
        } finally {
            $effects?->hooks->useMods([]);
        }
        $services->globals->setStepParticipants([]);
        $this->ctx->record(...ActionEvents::fromCommit($actionNo, $result));
        $notices = (new OutcomeNotices($this->ctx))->commit($actionNo, $mode, $result, $auto, $before, $mark);

        if ($effects !== null) {
            $effectNotices = new EffectNotices($this->ctx);
            foreach ($result->outcomes as $outcome) {
                $keys = $keysByPlayer[$outcome->playerId] ?? [];
                if ($keys === [] || $outcome->isPass()) {
                    continue;
                }
                $effects->useMods->commit($keys);
                foreach ($keys as $key) {
                    $notices[] = $effectNotices->used($outcome->playerId, $key);
                }
            }
            // 企画のオプション（P068・P070・P072）などの、効果エンジンの書き込みの結果
            $built = $effectNotices->build(0, [], $effects->game->journalSince($journalMark));
            array_push($notices, ...$built['held'], ...$built['private']);
        }

        return $notices;
    }
}
