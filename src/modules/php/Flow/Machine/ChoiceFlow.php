<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Flow\Action\ActionStep;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Action\StepEntry;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Flow\Action\StepRunner;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Setup\MulliganStep;

/**
 * 同時選択の状態 1 つ分（13・22・61・71・72・81・82・91）の共通パターン（009 §5.2）を、Table に依存せずに行う。
 * 状態クラスは enter / confirm / zombie / args を呼び、フレームワークの操作（setPlayersMultiactive など）と通知の送信だけを行う。
 */
final class ChoiceFlow
{
    /**
     * @param int|null $actionNo アクション番号（2〜5）。マリガンと計画は null
     */
    public function __construct(
        private readonly FlowContext $ctx,
        public readonly int $stateId,
        public readonly ActionStep $step,
        public readonly StepMode $mode,
        public readonly ?int $actionNo,
    ) {
    }

    /**
     * 状態 ID から作る。
     */
    public static function forState(FlowContext $ctx, int $stateId): self
    {
        $phases = $ctx->phases;
        return match ($stateId) {
            StateId::SETUP_MULLIGAN => new self($ctx, $stateId, new MulliganStep($ctx->services), StepMode::Main, null),
            StateId::PLANNING_PLOT => new self($ctx, $stateId, $phases->planning(), StepMode::Main, null),
            StateId::DEPLOY_MAIN_CHOOSE => new self($ctx, $stateId, $phases->deploy(), StepMode::Main, 2),
            StateId::LAUNCH_MAIN_CHOOSE => new self($ctx, $stateId, $phases->launch(), StepMode::Main, 3),
            StateId::LAUNCH_BONUS_CHOOSE => new self($ctx, $stateId, $phases->launch(), StepMode::Bonus, 3),
            StateId::LINK_MAIN_CHOOSE => new self($ctx, $stateId, $phases->link(), StepMode::Main, 4),
            StateId::LINK_BONUS_CHOOSE => new self($ctx, $stateId, $phases->link(), StepMode::Bonus, 4),
            StateId::PROPOSAL_KEEP_CHOOSE => new self($ctx, $stateId, $phases->proposal(), StepMode::Main, 5),
            default => throw new \InvalidArgumentException("state $stateId is not a simultaneous choice"),
        };
    }

    private function runner(): StepRunner
    {
        return $this->ctx->phases->runner();
    }

    /**
     * 状態に入る。参加者を決め、前回の確定を消し、行動できない人を自動で確定する。
     *
     * @return array{0: StepEntry, 1: list<Notice>} 入場の結果と、自動パスの通知
     */
    public function enter(): array
    {
        $entry = $this->runner()->enter($this->step, $this->stateId, $this->mode);
        $notices = [];
        if ($this->actionNo !== null) {
            $notices = (new OutcomeNotices($this->ctx))->autoPassed($this->actionNo, $this->mode, $entry->autoConfirmed);
        }
        return [$entry, $notices];
    }

    /**
     * 確定（何度でも上書きできる）。
     *
     * @param array<mixed> $data
     * @throws RuleViolation
     */
    public function confirm(int $playerId, array $data): ConfirmResult
    {
        $first = !$this->ctx->services->choices->isConfirmed($playerId, $this->stateId);
        // 起動の修正（USE_MODS）は、使う鍵を検証してから、この確定の検証の間だけコストの問い合わせに効かせる（011 §6-3）
        $effects = $this->ctx->effects;
        $keys = [];
        if ($effects !== null && $this->actionNo === 2) {
            $keys = $effects->stepMods->validateDeploy($playerId, $data);
        } elseif ($effects !== null && $this->actionNo !== null) {
            $keys = $effects->stepMods->validate($this->actionNo, $playerId, $data);
        }
        $effects?->hooks->useMods($keys);
        try {
            $all = $this->runner()->confirm($this->step, $this->stateId, $playerId, $data, $this->mode);
        } finally {
            $effects?->hooks->useMods([]);
        }
        $notices = [$this->ctx->notices->choiceRecorded($playerId, $this->stateId, $data)];
        if ($first) {
            $notices[] = $this->ctx->notices->choiceMarked($playerId, $this->stateId);
        }
        return new ConfirmResult($first, $all, $notices);
    }

    /**
     * zombie: 既定の選択を確定する（009 §9.2）。
     */
    public function zombie(int $playerId): ConfirmResult
    {
        $first = !$this->ctx->services->choices->isConfirmed($playerId, $this->stateId);
        $all = $this->runner()->zombie($this->step, $this->stateId, $playerId, $this->mode);
        $notices = $first ? [$this->ctx->notices->choiceMarked($playerId, $this->stateId)] : [];
        return new ConfirmResult($first, $all, $notices);
    }

    /**
     * 状態の args（010 §13）。公開部分は参加者と確定済みの ID だけ。本人の確定内容と候補は _private。DB は書かない。
     *
     * @return array<string, mixed>
     */
    public function args(): array
    {
        return (new ChoiceArgs($this->ctx))->build($this);
    }
}
