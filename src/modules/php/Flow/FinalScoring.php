<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow;

use Bga\Games\projectedgeworth\Board\BoardGeometry;
use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Data\GlobalValues;
use Bga\Games\projectedgeworth\Data\LinkRepository;
use Bga\Games\projectedgeworth\Data\MissionRepository;
use Bga\Games\projectedgeworth\Data\PlayerValues;
use Bga\Games\projectedgeworth\Data\ProjectCardRepository;
use Bga\Games\projectedgeworth\Data\RegionRepository;
use Bga\Games\projectedgeworth\Data\SatelliteRepository;
use Bga\Games\projectedgeworth\Scoring\FameCalculator;
use Bga\Games\projectedgeworth\Scoring\FameResult;
use Bga\Games\projectedgeworth\Scoring\LinkedCube;
use Bga\Games\projectedgeworth\Scoring\PlayerState;
use Bga\Games\projectedgeworth\Scoring\SatelliteState;
use Bga\Games\projectedgeworth\Scoring\ScoringBoard;

/**
 * 最終得点（009 の 121 FinalScoring）。DB の状態から PlayerState を組み立て、FameCalculator に渡して
 * 内訳を player.fame_detail に保存する。手札の換金（Q-12）は FameCalculator が計算上で行う。
 * player_score への書き込みと通知は呼び出し側（Game / 状態クラス）が、返り値の total で行う。
 */
final class FinalScoring
{
    public function __construct(
        private readonly SatelliteRepository $satellites,
        private readonly ProjectCardRepository $projectCards,
        private readonly LinkRepository $links,
        private readonly MissionRepository $missions,
        private readonly RegionRepository $regions,
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
    ) {
    }

    /**
     * 全員の名声を計算して fame_detail を保存する。
     *
     * @return array<int, FameResult> プレイヤー ID => 結果（席順）
     */
    public function run(): array
    {
        $board = $this->scoringBoard();
        $results = FameCalculator::calculate($this->playerStates(), $board, $this->globals->rewardCards());
        foreach ($results as $playerId => $result) {
            $this->players->setFameDetail($playerId, $result->detail);
        }
        return $results;
    }

    /**
     * @return list<PlayerState> 席順
     */
    public function playerStates(): array
    {
        $endPlayers = $this->globals->endConditionPlayers();
        $states = [];
        foreach ($this->players->playerIds() as $id) {
            $sats = [];
            foreach ($this->satellites->forPlayer($id) as $sat) {
                $sats[] = new SatelliteState(
                    $sat->id,
                    $sat->cardId,
                    $sat->isActive(),
                    $sat->fuelUsed,
                    array_map(
                        static fn(int $cardId): string => sprintf('P%03d', $cardId),
                        $this->projectCards->deployedIds($sat->id),
                    ),
                );
            }
            $cubes = array_map(
                static fn($link): LinkedCube => new LinkedCube($link->cell, $link->slot),
                $this->links->forPlayer($id),
            );
            $states[] = new PlayerState(
                $id,
                $this->players->budget($id),
                $this->players->maya($id),
                $this->projectCards->countIn(CardLocation::Hand, $id),
                $cubes,
                $this->players->padCell($id),
                $sats,
                $this->missions->forPlayer($id),
                in_array($id, $endPlayers, true),
            );
        }
        return $states;
    }

    public function scoringBoard(): ScoringBoard
    {
        $placements = [];
        foreach ($this->regions->all() as $r) {
            $placements[$r['row']][$r['col']] = ['boardId' => $r['board'], 'flip' => $r['flipped']];
        }
        ksort($placements);
        $rows = [];
        foreach ($placements as $row) {
            ksort($row);
            $rows[] = array_values($row);
        }
        $geometry = new BoardGeometry($this->globals->boardCols(), $this->globals->rowShift());
        return ScoringBoard::fromLayout(new BoardLayout($this->globals->boardFace(), $geometry, $rows));
    }
}
