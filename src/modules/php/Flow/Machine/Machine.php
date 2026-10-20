<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\StateId;

/**
 * 状態機械のルール層への入口。状態クラス（States/）は、ここから各フローを取り出して呼ぶだけにする。
 * BGA に依存しないので、状態クラスを使わずに通しで回す結合テストができる。
 */
final class Machine
{
    private ?SetupFlow $setup = null;
    private ?RoundFlow $rounds = null;
    private ?PhaseDrivers $drivers = null;
    private ?OrbitDriver $orbit = null;
    private ?EndFlow $end = null;
    private ?WindowFlow $windows = null;

    public function __construct(public readonly FlowContext $ctx)
    {
    }

    public function setup(): SetupFlow
    {
        return $this->setup ??= new SetupFlow($this->ctx);
    }

    public function rounds(): RoundFlow
    {
        return $this->rounds ??= new RoundFlow($this->ctx);
    }

    public function drivers(): PhaseDrivers
    {
        return $this->drivers ??= new PhaseDrivers($this->ctx);
    }

    public function orbit(): OrbitDriver
    {
        return $this->orbit ??= new OrbitDriver($this->ctx, $this->ctx->orbitMods);
    }

    public function end(): EndFlow
    {
        return $this->end ??= new EndFlow($this->ctx);
    }

    public function windows(): WindowFlow
    {
        return $this->windows ??= new WindowFlow($this->ctx);
    }

    public function choice(int $stateId): ChoiceFlow
    {
        return ChoiceFlow::forState($this->ctx, $stateId);
    }

    /**
     * 売却（actSell。009 §8）。売れるのは、売却可の状態の参加者が、手札から、自分の確定内容に含まれないカードを売るときだけ。
     * 売却額は P077・R009 などの効果を反映してサーバーが計算する。取り消せない。
     *
     * @param array<mixed> $cardIds
     * @param int $stateId 現在の状態
     * @return list<Notice>
     * @throws RuleViolation
     */
    public function sell(int $playerId, array $cardIds, int $stateId): array
    {
        $services = $this->ctx->services;
        if ($stateId === StateId::EFFECT_WINDOW_RESOLVE) {
            return $this->windows()->sell($playerId, ChoiceData::ids($cardIds));
        }
        if ($stateId === StateId::SETUP_LAUNCH_PAD_PLACE) {
            if ($this->setup()->nextPlacer() !== $playerId) {
                throw new RuleViolation('You cannot sell cards now');
            }
        } elseif (!in_array($stateId, self::sellableStates(), true) || !in_array($playerId, $services->globals->stepParticipants(), true)) {
            throw new RuleViolation('You cannot sell cards now');
        }

        $reserved = [];
        $own = $services->choices->ownChoice($playerId, $stateId);
        if ($own !== null) {
            foreach ((array) ($own['sell'] ?? []) as $id) {
                $reserved[] = (int) $id;
            }
            if (isset($own['card'])) {
                $reserved[] = (int) $own['card'];
            }
        }
        $ids = array_values(array_map('intval', $cardIds));
        $mark = $this->ctx->reshuffleMark();
        $gain = $services->selling->sell($playerId, $ids, $reserved);
        $notices = $this->ctx->reshuffleNotices($mark);
        $notices[] = $this->ctx->notices->cardsDiscarded($playerId, $ids, 'sell', $this->ctx->discardCount());
        $mayaChanged = $this->ctx->notices->mayaChanged($playerId, $gain, $services->players->maya($playerId), 'sell');
        if ($mayaChanged !== null) {
            $notices[] = $mayaChanged;
        }
        $notices[] = $this->ctx->counters();
        return $notices;
    }

    /**
     * 売却が可能な同時選択の状態（009 §8 の表。11 は別に扱う。31 は WindowFlow::sell が扱う）。
     *
     * @return list<int>
     */
    public static function sellableStates(): array
    {
        return [
            StateId::SETUP_MULLIGAN,
            StateId::PLANNING_PLOT,
            StateId::DEPLOY_MAIN_CHOOSE,
            StateId::DEPLOY_BONUS_CHOOSE,
            StateId::LAUNCH_MAIN_CHOOSE,
            StateId::LAUNCH_BONUS_CHOOSE,
            StateId::LINK_MAIN_CHOOSE,
            StateId::LINK_BONUS_CHOOSE,
            StateId::PROPOSAL_KEEP_CHOOSE,
            StateId::EFFECT_WINDOW_RESOLVE,
        ];
    }
}
