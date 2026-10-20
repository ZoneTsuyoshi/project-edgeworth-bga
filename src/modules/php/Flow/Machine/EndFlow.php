<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Data\FlowPhase;
use Bga\Games\projectedgeworth\Data\MissionRepository;
use Bga\Games\projectedgeworth\Flow\EndCondition;
use Bga\Games\projectedgeworth\Flow\FinalScoring;
use Bga\Games\projectedgeworth\Flow\RoundEnd;
use Bga\Games\projectedgeworth\Scoring\HandCashOut;

/**
 * 終了判定と、ゲームの終了（110・120・121。009 §3.8・§3.9）。
 */
final class EndFlow
{
    public function __construct(private readonly FlowContext $ctx)
    {
    }

    private function roundEnd(): RoundEnd
    {
        $s = $this->ctx->services;
        return new RoundEnd($s->satellites, $s->links, $s->players, $s->globals);
    }

    private function finalScoring(): FinalScoring
    {
        return $this->ctx->finalScoring();
    }

    /**
     * ゲーム進行度（0〜99。009 §10）。最高値を保存して単調増加にする。
     */
    public function progression(): int
    {
        return $this->roundEnd()->gameProgression();
    }

    /**
     * 110: 終了判定（001 §10）。終了なら N31 を出して 120 へ、そうでなければ次のラウンド（20）へ。
     */
    public function check(): DriverResult
    {
        $roundEnd = $this->roundEnd();
        if (!$roundEnd->check()) {
            return new DriverResult('nextRound');
        }
        $players = $this->ctx->globals->endConditionPlayers();
        $reasons = [];
        foreach ($roundEnd->playerProgress() as $p) {
            if (!in_array($p->playerId, $players, true)) {
                continue;
            }
            if ($p->activeSatellites >= EndCondition::SATELLITES && !in_array('satellites', $reasons, true)) {
                $reasons[] = 'satellites';
            }
            if ($p->links >= EndCondition::CUBES && !in_array('links', $reasons, true)) {
                $reasons[] = 'links';
            }
        }
        $this->ctx->globals->setFlowPhase(FlowPhase::End);
        return new DriverResult('end', [$this->ctx->notices->gameEndTriggered($players, $reasons)]);
    }

    /**
     * 120: 全員の手札を 1 枚 3 マヤで自動換金する（P077・R009 の増額を反映。Q-12、007 未確定 14）。zombie のプレイヤーも同じ。
     * 手札は捨て札へ。換金後は手札が 0 枚なので、121 の名声計算（FameCalculator の計算上の換金）と二重にならない（申し送り N-3）。
     */
    public function cashOut(): DriverResult
    {
        $services = $this->ctx->services;
        $states = [];
        foreach ($this->finalScoring()->playerStates() as $state) {
            $states[$state->playerId] = $state;
        }
        $notes = new NoticeList();
        foreach ($services->players->playerIds() as $playerId) {
            $hand = $services->cards->idsIn(CardLocation::Hand, $playerId);
            if ($hand === []) {
                continue;
            }
            $gain = HandCashOut::mayaFor($states[$playerId]);
            $services->cards->discard($hand);
            $maya = $services->players->addMaya($playerId, $gain);
            $notes->add($this->ctx->notices->handCashedOut($playerId, $hand, $gain, $maya));
        }
        $notes->add(...$this->ctx->sync());
        return new DriverResult('scoring', $notes->all());
    }

    /**
     * 121: 名声 I〜IX を計算し、内訳を保存して、全員分の N32 を席順に作る。player_score への書き込みは呼び出し側。
     */
    public function score(): FinalScoreOutcome
    {
        $results = $this->finalScoring()->run();
        $missions = new MissionRepository($this->ctx->db);
        $notices = [];
        $scores = [];
        foreach ($this->ctx->services->players->playerIds() as $playerId) {
            $result = $results[$playerId];
            $achieved = [];
            /** @var array{missions?: list<array{mission: int}>} $five */
            $five = (array) ($result->detail['V'] ?? []);
            foreach ($five['missions'] ?? [] as $row) {
                $achieved[] = (int) $row['mission'];
            }
            $list = array_map(
                static fn(int $no): array => ['mission' => $no, 'achieved' => in_array($no, $achieved, true)],
                $missions->forPlayer($playerId),
            );
            $notices[] = $this->ctx->notices->finalScore($playerId, $result->total, $result->detail, $list);
            $scores[$playerId] = $result->total;
        }
        return new FinalScoreOutcome($notices, $scores);
    }
}
