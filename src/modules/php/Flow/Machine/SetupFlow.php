<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Data\FlowPhase;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Setup\LaunchPadPlacement;
use Bga\Games\projectedgeworth\Setup\StartingHand;

/**
 * セットアップの状態 10〜17 の処理（009 §3.3）。
 */
final class SetupFlow
{
    public function __construct(private readonly FlowContext $ctx)
    {
    }

    /** 10: 次に発射拠点を置くプレイヤー（席順で未配置の最初の 1 人）。全員配置済みなら null */
    public function nextPlacer(): ?int
    {
        return LaunchPadPlacement::nextPlayer($this->ctx->services->players);
    }

    /**
     * 11: 空いている陸系の区域（区域 ID の昇順）。公開情報。
     *
     * @return list<int>
     */
    public function vacantCells(): array
    {
        return LaunchPadPlacement::placeableCells($this->ctx->services->layout, array_values($this->ctx->services->players->padCells()));
    }

    /**
     * 11: 発射拠点を置く。
     *
     * @return list<Notice>
     * @throws RuleViolation
     */
    public function placeLaunchBase(int $playerId, int $cell): array
    {
        if ($this->nextPlacer() !== $playerId) {
            throw new RuleViolation('It is not your turn to place a launch base');
        }
        if (!in_array($cell, $this->vacantCells(), true)) {
            throw new RuleViolation('Select a vacant land area');
        }
        $this->ctx->services->players->setPadCell($playerId, $cell);
        return [$this->ctx->notices->launchBasePlaced($playerId, $cell)];
    }

    /**
     * 11 の zombie: 他の発射拠点までの最短距離が最大の空き陸区域（同点はランダム。009 §9.2）。
     *
     * @return list<Notice>
     */
    public function zombiePlace(int $playerId): array
    {
        $cell = LaunchPadPlacement::zombieChoice(
            $this->ctx->services->layout,
            array_values($this->ctx->services->players->padCells()),
            $this->ctx->random,
        );
        if ($cell === null) {
            throw new \LogicException('no vacant land area for a launch base');
        }
        return $this->placeLaunchBase($playerId, $cell);
    }

    /**
     * 12: ゲームオプションで分岐。ドラフトは初版では未実装（GameOptions が受け付けない）。
     */
    public function route(StartingHand $option): string
    {
        return $option === StartingHand::Mulligan ? 'mulligan' : 'none';
    }

    /**
     * 14: 全員の確定を受けて、捨てて引き直す。
     */
    public function applyMulligan(): DriverResult
    {
        $flow = ChoiceFlow::forState($this->ctx, StateId::SETUP_MULLIGAN);
        $mark = $this->ctx->reshuffleMark();
        $result = $this->ctx->phases->runner()->resolve($flow->step, $flow->stateId, StepMode::Main);
        $notes = new NoticeList();
        $notes->addAll($this->ctx->reshuffleNotices($mark));
        foreach ($result->outcomes as $outcome) {
            if ($outcome->isPass()) {
                continue;
            }
            $notes->add($this->ctx->notices->cardsDiscarded(
                $outcome->playerId,
                array_values(array_map('intval', $outcome->details['discarded'])),
                'mulligan',
                $this->ctx->discardCount(),
            ));
            $notes->addAll($this->ctx->notices->cardsDrawn(
                $outcome->playerId,
                array_values(array_map('intval', $outcome->details['drawn'])),
                'hand',
                'mulligan',
                $this->ctx->deckCount(),
                $this->ctx->discardCount(),
            ));
        }
        $notes->add(...$this->ctx->sync());
        return new DriverResult('done', $notes->all());
    }

    /**
     * 17: セットアップの最後。セットアップの配備フェイズ（001 §5 手順 16）に入る準備をする。
     * 全員がメインとボーナスの両方に参加する（DeployPhase は round_no = 0 のとき全員を参加者にする）。
     * flow_phase は SETUP のままにしておき、40 が II 配備の開始時に DEPLOY にする（セットアップ中かどうかの判別に使う）。
     */
    public function finish(): DriverResult
    {
        $globals = $this->ctx->globals;
        $globals->setRoundNo(0);
        $globals->setFlowPhase(FlowPhase::Setup);
        $globals->setFlowStep(0);
        $globals->setBonusHolders([2 => $this->ctx->services->players->playerIds()]);
        return new DriverResult('start');
    }
}
