<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

use Bga\GameFramework\Db\Globals;
use Bga\Games\projectedgeworth\Board\BoardFace;

/**
 * ゲーム全体の値（$this->bga->globals。010 §7.2）のアクセサ。キー名と初期値は 010 §7.2 の表に合わせる。
 * 値が未設定のときは初期値を返す。
 */
final class GlobalValues
{
    public function __construct(private readonly Globals $globals)
    {
    }

    /**
     * 010 §7.2 の初期値を書く（setupNewGame 用）。board_cols・board_face・row_shift・reward_cards・base_player は
     * セットアップが決めて別に書く。
     */
    public function initialize(): void
    {
        $this->setRoundNo(0);
        $this->setFlowPhase(FlowPhase::Setup);
        $this->setFlowStep(0);
        $this->setFlowContext([]);
        $this->setWindowId(null);
        $this->setWindowResume(null);
        $this->setWindowGen(0);
        $this->setWindowEffects(0);
        $this->setWindowSnapshot(null);
        $this->setCommitSeq(0);
        $this->setHeldSeq(0);
        $this->setBonusHolders([]);
        $this->setStepParticipants([]);
        $this->setEndConditionPlayers([]);
        $this->setProgressHighWater(0);
    }

    public function roundNo(): int
    {
        return (int) $this->globals->get('round_no', 0);
    }

    public function setRoundNo(int $value): void
    {
        $this->globals->set('round_no', $value);
    }

    public function flowPhase(): FlowPhase
    {
        return FlowPhase::tryFrom((string) $this->globals->get('flow_phase', '')) ?? FlowPhase::Setup;
    }

    public function setFlowPhase(FlowPhase $phase): void
    {
        $this->globals->set('flow_phase', $phase->value);
    }

    public function flowStep(): int
    {
        return (int) $this->globals->get('flow_step', 0);
    }

    public function setFlowStep(int $value): void
    {
        $this->globals->set('flow_step', $value);
    }

    /**
     * @return array<mixed>
     */
    public function flowContext(): array
    {
        return $this->arrayValue('flow_ctx');
    }

    /**
     * @param array<mixed> $value
     */
    public function setFlowContext(array $value): void
    {
        $this->globals->set('flow_ctx', (object) $value);
    }

    public function windowId(): ?string
    {
        return $this->nullableString('window_id');
    }

    public function setWindowId(?string $value): void
    {
        $this->globals->set('window_id', $value);
    }

    public function windowResume(): ?int
    {
        return $this->nullableInt('window_resume');
    }

    public function setWindowResume(?int $stateId): void
    {
        $this->globals->set('window_resume', $stateId);
    }

    public function windowGen(): int
    {
        return (int) $this->globals->get('window_gen', 0);
    }

    public function setWindowGen(int $value): void
    {
        $this->globals->set('window_gen', $value);
    }

    public function incWindowGen(): int
    {
        return $this->globals->inc('window_gen', 1);
    }

    public function windowEffects(): int
    {
        return (int) $this->globals->get('window_effects', 0);
    }

    public function setWindowEffects(int $value): void
    {
        $this->globals->set('window_effects', $value);
    }

    public function incWindowEffects(): int
    {
        return $this->globals->inc('window_effects', 1);
    }

    /**
     * 窓を開いた時点の公開情報（010 §9.4）。窓の外では null。
     *
     * @return array<mixed>|null
     */
    public function windowSnapshot(): ?array
    {
        return $this->globals->get('window_snapshot') === null ? null : $this->arrayValue('window_snapshot');
    }

    /**
     * @param array<mixed>|null $value
     */
    public function setWindowSnapshot(?array $value): void
    {
        $this->globals->set('window_snapshot', $value);
    }

    public function commitSeq(): int
    {
        return (int) $this->globals->get('commit_seq', 0);
    }

    public function setCommitSeq(int $value): void
    {
        $this->globals->set('commit_seq', $value);
    }

    /**
     * 通信確立のコミット番号を 1 つ進めて返す。
     */
    public function incCommitSeq(): int
    {
        return $this->globals->inc('commit_seq', 1);
    }

    public function heldSeq(): int
    {
        return (int) $this->globals->get('held_seq', 0);
    }

    public function setHeldSeq(int $value): void
    {
        $this->globals->set('held_seq', $value);
    }

    /**
     * 保留通知の ev_id を 1 つ進めて返す。
     */
    public function incHeldSeq(): int
    {
        return $this->globals->inc('held_seq', 1);
    }

    /**
     * アクション選択ボーナスの保持者。キーが存在するアクション = 選ばれたアクション。
     *
     * @return array<int, list<int>> アクション番号 => プレイヤー ID
     */
    public function bonusHolders(): array
    {
        $result = [];
        foreach ($this->arrayValue('bonus_holders') as $action => $holders) {
            $result[(int) $action] = array_map('intval', array_values((array) $holders));
        }
        return $result;
    }

    /**
     * @param array<int, list<int>> $value
     */
    public function setBonusHolders(array $value): void
    {
        $this->globals->set('bonus_holders', (object) $value);
    }

    /**
     * 選ばれたアクションの番号（bonus_holders のキー集合。昇順）。
     *
     * @return list<int>
     */
    public function selectedActions(): array
    {
        $keys = array_keys($this->bonusHolders());
        sort($keys);
        return $keys;
    }

    /**
     * @return list<int>
     */
    public function stepParticipants(): array
    {
        return $this->intList('step_participants');
    }

    /**
     * @param list<int> $value
     */
    public function setStepParticipants(array $value): void
    {
        $this->globals->set('step_participants', array_values($value));
    }

    public function basePlayer(): int
    {
        return (int) $this->globals->get('base_player', 0);
    }

    public function setBasePlayer(int $playerId): void
    {
        $this->globals->set('base_player', $playerId);
    }

    /**
     * 盤面の列数 W（12 / 9 / 6）。未設定は 0。
     */
    public function boardCols(): int
    {
        return (int) $this->globals->get('board_cols', 0);
    }

    public function setBoardCols(int $value): void
    {
        $this->globals->set('board_cols', $value);
    }

    public function boardFace(): BoardFace
    {
        return $this->globals->get('board_face') === 'ADVANCED' ? BoardFace::ADVANCED : BoardFace::STANDARD;
    }

    public function setBoardFace(BoardFace $face): void
    {
        $this->globals->set('board_face', $face->name);
    }

    /**
     * 行のずれ P（0 / 1）。
     */
    public function rowShift(): int
    {
        return (int) $this->globals->get('row_shift', 0);
    }

    public function setRowShift(int $value): void
    {
        if ($value !== 0 && $value !== 1) {
            throw new \InvalidArgumentException("row_shift must be 0 or 1: $value");
        }
        $this->globals->set('row_shift', $value);
    }

    /**
     * 使う褒賞の 2 面（T001〜T024）。
     *
     * @return list<string>
     */
    public function rewardCards(): array
    {
        $v = $this->globals->get('reward_cards', []);
        return is_array($v) ? array_map('strval', array_values($v)) : [];
    }

    /**
     * @param list<string> $value
     */
    public function setRewardCards(array $value): void
    {
        $this->globals->set('reward_cards', array_values($value));
    }

    /**
     * @return list<int>
     */
    public function endConditionPlayers(): array
    {
        return $this->intList('end_condition_players');
    }

    /**
     * @param list<int> $value
     */
    public function setEndConditionPlayers(array $value): void
    {
        $this->globals->set('end_condition_players', array_values($value));
    }

    public function progressHighWater(): int
    {
        return (int) $this->globals->get('progress_high_water', 0);
    }

    public function setProgressHighWater(int $value): void
    {
        $this->globals->set('progress_high_water', $value);
    }

    /**
     * @return array<mixed>
     */
    private function arrayValue(string $name): array
    {
        $v = $this->globals->get($name, []);
        if (is_object($v)) {
            // フレームワークが空の連想配列を stdClass で返す場合に備え、配列へそろえる
            $v = json_decode(json_encode($v, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        }
        return is_array($v) ? $v : [];
    }

    /**
     * @return list<int>
     */
    private function intList(string $name): array
    {
        $v = $this->globals->get($name, []);
        return is_array($v) ? array_map('intval', array_values($v)) : [];
    }

    private function nullableInt(string $name): ?int
    {
        $v = $this->globals->get($name);
        return $v === null ? null : (int) $v;
    }

    private function nullableString(string $name): ?string
    {
        $v = $this->globals->get($name);
        return $v === null ? null : (string) $v;
    }
}
