<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Scoring;

use Bga\Games\projectedgeworth\Board\AreaType;
use Bga\Games\projectedgeworth\Material\RewardCards;
use Bga\Games\projectedgeworth\Scoring\LinkedCube;
use Bga\Games\projectedgeworth\Scoring\RewardComparison;
use PHPUnit\Framework\TestCase;

final class RewardComparisonTest extends TestCase
{
    private function c(int $row, int $col): int
    {
        return Fixtures::cell($row, $col);
    }

    /**
     * 汎用の状態: 海 (0,0)(0,1)(0,5)(0,23) と陸 (0,10)、発射拠点 (3,10)（陸）。
     * (0,23)-(0,0)-(0,1) は左右の端でつながる。
     *
     * @return array{\Bga\Games\projectedgeworth\Scoring\ScoringBoard, \Bga\Games\projectedgeworth\Scoring\PlayerState}
     */
    private function generic(): array
    {
        $board = Fixtures::board([$this->c(0, 10) => AreaType::LAND, $this->c(3, 10) => AreaType::LAND]);
        $player = Fixtures::player(
            1,
            budget: 17,
            maya: 42,
            hand: 5,
            cubes: [
                new LinkedCube($this->c(0, 0), 0),
                new LinkedCube($this->c(0, 1), 2),
                new LinkedCube($this->c(0, 5), 1),
                new LinkedCube($this->c(0, 10), 0),
                new LinkedCube($this->c(0, 23), 3),
            ],
            pad: $this->c(3, 10),
        );
        return [$board, $player];
    }

    public function testEveryCardHasAComparison(): void
    {
        [$board, $player] = $this->generic();
        foreach (array_keys(RewardCards::all()) as $id) {
            $this->assertIsInt(RewardComparison::value($id, $player, $board, []));
        }
        $this->expectException(\OutOfBoundsException::class);
        RewardComparison::value('T999', $player, $board, []);
    }

    public function testT001SeaAndT002LandIncludeLaunchPad(): void
    {
        [$board, $p] = $this->generic();
        $this->assertSame(4, RewardComparison::value('T001', $p, $board));
        // 陸 = 陸のキューブ 1 + 発射拠点 1（FAQ 行 79）
        $this->assertSame(2, RewardComparison::value('T002', $p, $board));
    }

    public function testT003IndependentGroupsAndT004LargestChain(): void
    {
        [$board, $p] = $this->generic();
        // {(0,23),(0,0),(0,1)}（左右がつながる）、{(0,5)}（孤立も 1 つ。FAQ 行 81）、{(0,10)}、{(3,10)} は
        // (0,10) と (3,10) が離れているので別
        $this->assertSame(4, RewardComparison::value('T003', $p, $board));
        $this->assertSame(3, RewardComparison::value('T004', $p, $board));
    }

    public function testT005AndT006Regions(): void
    {
        [$board, $p] = $this->generic();
        // 地域: (0,0)(0,1)=A、(0,5)=B、(0,10)=D、(3,10)=D、(0,23)=H
        $this->assertSame(2, RewardComparison::value('T005', $p, $board)); // A ×2
        $this->assertSame(4, RewardComparison::value('T006', $p, $board)); // B, D, D, H
    }

    public function testT007CountsOnlyCubesNotInFirstSlot(): void
    {
        [$board, $p] = $this->generic();
        // slot 0 は 1 番目（同時に重ねた区域を含む）。slot 2, 1, 3 の 3 つ。発射拠点は数えない（FAQ 行 82）
        $this->assertSame(3, RewardComparison::value('T007', $p, $board));
    }

    public function testT007CountsACubeOnAnotherPlayersLaunchBaseCellAsNotFirst(): void
    {
        $board = Fixtures::board();
        // 他人の発射拠点が slot 0 を占める区域では、最初に確立したキューブも slot 1（2 番目以降）。自分の発射拠点は数えない
        $p = Fixtures::player(
            1,
            cubes: [new LinkedCube($this->c(1, 2), 1), new LinkedCube($this->c(0, 3), 0)],
            pad: $this->c(3, 10),
        );
        $this->assertSame(1, RewardComparison::value('T007', $p, $board));
    }

    public function testT008AdjacentToAnyLaunchPadExcludingOwn(): void
    {
        $board = Fixtures::board();
        $own = $this->c(3, 10);
        $other = $this->c(1, 2);
        // 自分の拠点 (3,10) の両隣、他人の拠点 (1,2) の隣 (0,3)、どちらにも隣接しない (5,15)
        $p = Fixtures::player(
            1,
            cubes: [$this->c(3, 9), $this->c(3, 11), $this->c(0, 3), $this->c(5, 15)],
            pad: $own,
        );
        $this->assertSame(3, RewardComparison::value('T008', $p, $board, [$own, $other]));
        // 他人の拠点を知らなければ (0,3) は数えない
        $this->assertSame(2, RewardComparison::value('T008', $p, $board, [$own]));
    }

    public function testT009ToT012CountDeployedCardsIncludingInactive(): void
    {
        $board = Fixtures::board();
        $p = Fixtures::player(1, satellites: [
            Fixtures::sat(1, 'R001B', true, ['P001', 'P002', 'P034']),
            Fixtures::sat(2, 'R003B', false, ['P067', 'P035', 'P003']),
        ]);
        $this->assertSame(3, RewardComparison::value('T009', $p, $board)); // CREW: P001 P002 P003
        $this->assertSame(2, RewardComparison::value('T010', $p, $board)); // DEVICE: P034 P035
        $this->assertSame(1, RewardComparison::value('T011', $p, $board)); // SOFTWARE: P067（非稼働の衛星。FAQ 行 84）
        $this->assertSame(1, RewardComparison::value('T012', $p, $board)); // 非稼働の衛星 2 だけがそろう
    }

    public function testT013BudgetAndT014MayaIgnoreHand(): void
    {
        [$board, $p] = $this->generic();
        $this->assertSame(17, RewardComparison::value('T013', $p, $board));
        // ゲーム中は手札を換金しないので、所持マヤだけ（手札 5 枚は含まない）
        $this->assertSame(42, RewardComparison::value('T014', $p, $board));
    }

    public function testT015T016Fuel(): void
    {
        $board = Fixtures::board();
        $p = Fixtures::player(1, satellites: [
            // 容量 2 + 2 + 2 + 2 = 8、消費 3 → 残り 5
            Fixtures::sat(1, 'R001B', true, ['P001', 'P002', 'P034'], 3),
            // 容量 2、消費 3（= C + 1、庫外）→ 0（FAQ 行 85）
            Fixtures::sat(2, 'R002B', true, [], 3),
            // 非稼働は残燃料に数えない。容量は 2 + 2 + 2 + 3 = 9
            Fixtures::sat(3, 'R003B', false, ['P067', 'P035', 'P003'], 0),
        ]);
        $this->assertSame(5, RewardComparison::value('T015', $p, $board));
        // 非稼働の衛星も対象（FAQ 行 86）
        $this->assertSame(9, RewardComparison::value('T016', $p, $board));
    }

    /**
     * 行 2 の地形:
     * c0 山岳, c1 海, c2 深海, c3 岩礁, c4 陸, c5 森林, c6 海, c7 山岳, c8 海, c9 山岳, c10 雪原, c11 海, c12 岩礁, c13 陸
     * 所有: c1〜c6, c8, c11〜c13（山岳・雪原は所有しない）
     *
     * @return array{\Bga\Games\projectedgeworth\Scoring\ScoringBoard, \Bga\Games\projectedgeworth\Scoring\PlayerState}
     */
    private function terrain(): array
    {
        $types = [
            0 => AreaType::MOUNTAIN, 1 => AreaType::SEA, 2 => AreaType::DEEP_SEA, 3 => AreaType::REEF,
            4 => AreaType::LAND, 5 => AreaType::FOREST, 6 => AreaType::SEA, 7 => AreaType::MOUNTAIN,
            8 => AreaType::SEA, 9 => AreaType::MOUNTAIN, 10 => AreaType::SNOW, 11 => AreaType::SEA,
            12 => AreaType::REEF, 13 => AreaType::LAND,
        ];
        $map = [];
        foreach ($types as $col => $type) {
            $map[$this->c(2, $col)] = $type;
        }
        $owned = [];
        foreach ([1, 2, 3, 4, 5, 6, 8, 11, 12, 13] as $col) {
            $owned[] = $this->c(2, $col);
        }
        return [Fixtures::board($map), Fixtures::player(1, cubes: $owned)];
    }

    public function testT017ToT018StandardAreas(): void
    {
        [$board, $p] = $this->terrain();
        $this->assertSame(3, RewardComparison::value('T017', $p, $board)); // 標準陸 c4, c13 + 森林 c5
        $this->assertSame(4, RewardComparison::value('T018', $p, $board)); // 標準海 c1, c6, c8, c11（深海・岩礁は除く）
    }

    public function testT019ToT021T023AdjacentSeaAreas(): void
    {
        [$board, $p] = $this->terrain();
        // 山岳: c1（c0）、c6（c7）、c8（c7 と c9 の両方でも 1 回）。山岳自体は未所有でよい（FAQ 行 87）
        $this->assertSame(3, RewardComparison::value('T019', $p, $board));
        $this->assertSame(1, RewardComparison::value('T020', $p, $board)); // 雪原 c10 の隣の c11
        $this->assertSame(1, RewardComparison::value('T021', $p, $board)); // 森林 c5 の隣の c6
        // 深海 c2 の隣の c1 と、岩礁 c3（岩礁も海区域）
        $this->assertSame(2, RewardComparison::value('T023', $p, $board));
    }

    public function testT022MostAreaTypesInConnectedGroup(): void
    {
        [$board, $p] = $this->terrain();
        // c1〜c6 は 海・深海・岩礁・陸・森林・海 の 5 種。c11〜c13 は 3 種
        $this->assertSame(5, RewardComparison::value('T022', $p, $board));
    }

    public function testT024AdjacentLandAreas(): void
    {
        [$board, $p] = $this->terrain();
        // 岩礁 c3 の隣の陸 c4、岩礁 c12 の隣の陸 c13。森林 c5 は岩礁に隣接しない
        $this->assertSame(2, RewardComparison::value('T024', $p, $board));
    }
}
