<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Scoring;

use Bga\Games\projectedgeworth\Board\MissionMatcher;
use Bga\Games\projectedgeworth\Material\RewardCards;

/**
 * ゲーム終了時の名声計算（001 §12 の I〜IX）。BGA・DB に依存しない。
 */
final class FameCalculator
{
    /** 区分 IX: 終了条件を満たしたプレイヤー */
    public const END_CONDITION_FAME = 3;
    /** 区分 VII-c: CREW・DEVICE・SOFTWARE がそろった衛星 1 機あたり */
    public const COMPLETE_SATELLITE_FAME = 5;

    private function __construct()
    {
    }

    /**
     * 全員の最終名声を計算する。終了時に残った手札は先に 1 枚 3 マヤで換金する（Q-12）。
     *
     * @param list<PlayerState> $players
     * @param list<string> $rewardIds このゲームで使う褒賞カード（2 枚）
     * @return array<int, FameResult> プレイヤー ID => 結果
     */
    public static function calculate(array $players, ScoringBoard $board, array $rewardIds): array
    {
        $cashed = [];
        foreach ($players as $p) {
            $cashed[] = $p->withHandCashedOut();
        }

        $pads = [];
        foreach ($cashed as $p) {
            if ($p->launchPadCell !== null) {
                $pads[] = $p->launchPadCell;
            }
        }

        $rewards = [];
        foreach ($rewardIds as $rewardId) {
            $values = [];
            foreach ($cashed as $p) {
                $values[$p->playerId] = RewardComparison::value($rewardId, $p, $board, $pads);
            }
            $rewards[$rewardId] = ['values' => $values, 'ranking' => RewardRanking::award($values, RewardCards::get($rewardId)->points)];
        }

        $results = [];
        foreach ($cashed as $p) {
            $rewardRows = [];
            foreach ($rewards as $rewardId => $r) {
                $rewardRows[] = [
                    'card' => $rewardId,
                    'value' => $r['values'][$p->playerId],
                    'rank' => $r['ranking'][$p->playerId]['rank'],
                    'points' => $r['ranking'][$p->playerId]['points'],
                ];
            }
            $results[$p->playerId] = self::calculatePlayer($p, $board, $rewardRows);
        }
        return $results;
    }

    /**
     * 1 人分（手札は換金済みであること）。褒賞は順位の確定後の行を受け取る。
     *
     * @param list<array{card: string, value: int, rank: int, points: int}> $rewardRows
     */
    public static function calculatePlayer(PlayerState $player, ScoringBoard $board, array $rewardRows): FameResult
    {
        $cells = $player->linkedCells();

        $land = 0;
        $sea = 0;
        $regions = [];
        foreach ($cells as $id) {
            $type = $board->areaType($id);
            if ($type->isSea()) {
                $sea++;
            } else {
                $land++;
            }
            $regions[$board->region($id)->value] = true;
        }
        $regionNames = array_keys($regions);
        sort($regionNames);
        $regionCount = count($regionNames);

        $assignment = MissionMatcher::assign($board->geometry(), $player->missionIds, $cells);
        $missions = [];
        foreach ($assignment->results as $missionId => $cand) {
            if ($cand !== null) {
                $missions[] = ['mission' => $missionId, 'tier' => strtoupper($cand->tier->value), 'points' => $cand->fame, 'cells' => $cand->cells];
            }
        }

        $sats = [];
        $viSum = 0;
        $a = 0;
        $inactive = 0;
        foreach ($player->satellites as $sat) {
            if ($sat->active) {
                $viSum += $sat->satelliteFame();
                $sats[] = ['sat_id' => $sat->satId, 'fame' => $sat->satelliteFame()];
                $a += $sat->deployedFame();
            } else {
                $inactive += $sat->deployedFame();
            }
        }
        $b = intdiv($inactive, 2);
        $c = self::COMPLETE_SATELLITE_FAME * RewardComparison::completeSatellites($player);

        $viii = 0;
        foreach ($rewardRows as $row) {
            $viii += $row['points'];
        }

        $detail = [
            'I' => ['points' => $player->budget, 'budget' => $player->budget],
            'II' => ['points' => intdiv($player->maya, 10), 'maya' => $player->maya],
            'III' => ['points' => 2 * $land + $sea, 'land' => $land, 'sea' => $sea, 'cells' => $cells],
            'IV' => ['points' => intdiv($regionCount * ($regionCount + 1), 2), 'regions' => $regionNames],
            'V' => ['points' => $assignment->totalFame, 'missions' => $missions],
            'VI' => ['points' => $viSum, 'sats' => $sats],
            'VII' => ['points' => $a + $b + $c, 'a' => $a, 'b' => $b, 'c' => $c],
            'VIII' => ['points' => $viii, 'rewards' => $rewardRows],
            'IX' => ['points' => $player->endConditionMet ? self::END_CONDITION_FAME : 0],
        ];

        $total = 0;
        foreach ($detail as $section) {
            $total += $section['points'];
        }
        return new FameResult($player->playerId, $total, ['total' => $total] + $detail);
    }
}
