<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Scoring;

use Bga\Games\ProjectEdgeworth\Board\AreaType;
use Bga\Games\ProjectEdgeworth\Board\MissionShapes;
use Bga\Games\ProjectEdgeworth\Scoring\FameCalculator;
use Bga\Games\ProjectEdgeworth\Scoring\FameResult;
use Bga\Games\ProjectEdgeworth\Scoring\HandCashOut;
use Bga\Games\ProjectEdgeworth\Scoring\PlayerState;
use PHPUnit\Framework\TestCase;

final class FameCalculatorTest extends TestCase
{
    private function c(int $row, int $col): int
    {
        return Fixtures::cell($row, $col);
    }

    /**
     * 001 §12-1 のテレサ（青）。ARCH は 2 段階（〇+◎）、LOCK は 1 段階（〇）で、区域は重ならない。
     *
     * @return array{list<PlayerState>, list<int>, \Bga\Games\ProjectEdgeworth\Scoring\ScoringBoard}
     */
    private function teresaGame(): array
    {
        $arch = Fixtures::place(MissionShapes::get(3), true, 12, 0);
        $lock = Fixtures::place(MissionShapes::get(9), false, 36, 2);
        $pad = $this->c(4, 2);
        $landCubes = [$this->c(5, 10), $this->c(4, 14)];
        $seaExtras = [$this->c(5, 0), $this->c(4, 12), $this->c(5, 16), $this->c(4, 19), $this->c(5, 4)];
        $cubes = array_merge($arch, $lock, $landCubes, $seaExtras);

        $board = Fixtures::board([
            $pad => AreaType::LAND,
            $landCubes[0] => AreaType::LAND,
            $landCubes[1] => AreaType::LAND,
        ]);

        $teresa = Fixtures::player(
            1,
            budget: 28,
            maya: 33,
            cubes: $cubes,
            pad: $pad,
            satellites: [
                // 稼働中: HAMMER 5 + RETRO 5 + CONCORDE 10 = 20
                Fixtures::sat(11, 'R001B', true, ['P001', 'P034', 'P067']),     // 名声 2+2+2 = 6、3 種そろう
                Fixtures::sat(12, 'R002B', true, ['P017', 'P018']),             // 3+3 = 6
                Fixtures::sat(13, 'R004B', true, ['P026', 'P005']),             // 6+2 = 8
                // 非稼働: SCALLOP（1+1+1、3 種そろう）、STEALTH（6+1）。合計 10 の半分 = 5
                Fixtures::sat(14, 'R003B', false, ['P003', 'P041', 'P075']),
                Fixtures::sat(15, 'R005B', false, ['P033', 'P010']),
            ],
            missions: [3, 9],
            end: true,
        );

        // 海上覇権: テレサ 14、他 10・8・5 → 1 位。駆動覇権: テレサ DEVICE 2 枚、他 4・3・1 枚 → 3 位
        $sea = static fn(int $n): array => array_map(static fn(int $col): int => Fixtures::cell(3, $col), range(0, $n - 1));
        $others = [
            Fixtures::player(2, cubes: $sea(10), satellites: [Fixtures::sat(21, 'R001G', true, ['P036', 'P037', 'P038', 'P039'])]),
            Fixtures::player(3, cubes: $sea(8), satellites: [Fixtures::sat(31, 'R001R', true, ['P040', 'P042', 'P043'])]),
            Fixtures::player(4, cubes: $sea(5), satellites: [Fixtures::sat(41, 'R001Y', true, ['P044'])]),
        ];
        return [array_merge([$teresa], $others), $arch, $board];
    }

    public function testTeresaExampleTotals184(): void
    {
        [$players, , $board] = $this->teresaGame();
        $results = FameCalculator::calculate($players, $board, ['T001', 'T010']);
        $t = $results[1];

        $this->assertSame(28, $t->points('I'));
        $this->assertSame(3, $t->points('II'));
        $this->assertSame(20, $t->points('III'));
        $this->assertSame(28, $t->points('IV'));
        $this->assertSame(23, $t->points('V'));
        $this->assertSame(20, $t->points('VI'));
        $this->assertSame(35, $t->points('VII'));
        $this->assertSame(24, $t->points('VIII'));
        $this->assertSame(3, $t->points('IX'));
        $this->assertSame(184, $t->total);
    }

    public function testTeresaDetailMatchesFameDetailFormat(): void
    {
        [$players, $arch, $board] = $this->teresaGame();
        $d = FameCalculator::calculate($players, $board, ['T001', 'T010'])[1]->detail;

        $this->assertSame(184, $d['total']);
        $this->assertSame(['points' => 28, 'budget' => 28], $d['I']);
        $this->assertSame(['points' => 3, 'maya' => 33], $d['II']);
        $this->assertSame(3, $d['III']['land']);
        $this->assertSame(14, $d['III']['sea']);
        $this->assertCount(17, $d['III']['cells']);
        $this->assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G'], $d['IV']['regions']);

        $this->assertSame([3, 'EXTRA', 18], [$d['V']['missions'][0]['mission'], $d['V']['missions'][0]['tier'], $d['V']['missions'][0]['points']]);
        $this->assertSame($arch, $d['V']['missions'][0]['cells']);
        $this->assertSame([9, 'EASY', 5], [$d['V']['missions'][1]['mission'], $d['V']['missions'][1]['tier'], $d['V']['missions'][1]['points']]);

        $this->assertSame([['sat_id' => 11, 'fame' => 5], ['sat_id' => 12, 'fame' => 5], ['sat_id' => 13, 'fame' => 10]], $d['VI']['sats']);
        $this->assertSame(['points' => 35, 'a' => 20, 'b' => 5, 'c' => 10], $d['VII']);
        $this->assertSame(
            [
                ['card' => 'T001', 'value' => 14, 'rank' => 1, 'points' => 18],
                ['card' => 'T010', 'value' => 2, 'rank' => 3, 'points' => 6],
            ],
            $d['VIII']['rewards'],
        );
        $this->assertSame(['points' => 3], $d['IX']);
        $this->assertJson((new FameResult(1, 184, $d))->toJson());
    }

    public function testOpponentsGetNoEndConditionAndOwnRanks(): void
    {
        [$players, , $board] = $this->teresaGame();
        $results = FameCalculator::calculate($players, $board, ['T001', 'T010']);
        $this->assertSame(0, $results[2]->points('IX'));
        // プレイヤー 2: 海 10 → 2 位（12）、DEVICE 4 → 1 位（18）
        $this->assertSame(30, $results[2]->points('VIII'));
    }

    public function testMissionTwoTiers(): void
    {
        $board = Fixtures::board();
        $shape = MissionShapes::get(9);
        $easy = Fixtures::place($shape, false, 36, 2);
        $full = Fixtures::place($shape, true, 36, 2);

        $r = FameCalculator::calculate([Fixtures::player(1, cubes: $easy, missions: [9])], $board, [])[1];
        $this->assertSame(5, $r->points('V')); // 〇のみ
        $this->assertSame('EASY', $r->detail['V']['missions'][0]['tier']);

        $r = FameCalculator::calculate([Fixtures::player(1, cubes: $full, missions: [9])], $board, [])[1];
        $this->assertSame(24, $r->points('V')); // 〇+◎ のみ（5 は足されない。FAQ 行 90）
        $this->assertSame('EXTRA', $r->detail['V']['missions'][0]['tier']);
    }

    public function testMissionsCannotShareCubes(): void
    {
        $board = Fixtures::board();
        $arch = Fixtures::place(MissionShapes::get(3), true, 12, 0);
        // 同じ 6 区域で ARCH（18）と LOCK を両方は取れない。1 枚分だけ
        $r = FameCalculator::calculate([Fixtures::player(1, cubes: $arch, missions: [3, 9])], $board, [])[1];
        $this->assertSame(18, $r->points('V'));
    }

    public function testMissionUsesLaunchPadCell(): void
    {
        $board = Fixtures::board();
        $easy = Fixtures::place(MissionShapes::get(9), false, 36, 2);
        $pad = array_pop($easy);
        $r = FameCalculator::calculate([Fixtures::player(1, cubes: $easy, pad: $pad, missions: [9])], $board, [])[1];
        $this->assertSame(5, $r->points('V')); // FAQ 行 51
    }

    public function testMayaFameRoundsDown(): void
    {
        $board = Fixtures::board();
        foreach ([[0, 0], [9, 0], [10, 1], [19, 1], [99, 9], [100, 10]] as [$maya, $fame]) {
            $r = FameCalculator::calculate([Fixtures::player(1, maya: $maya)], $board, [])[1];
            $this->assertSame($fame, $r->points('II'), "maya {$maya}");
        }
    }

    public function testRegionTable(): void
    {
        $board = Fixtures::board();
        $expected = [0 => 0, 1 => 1, 2 => 3, 3 => 6, 4 => 10, 5 => 15, 6 => 21, 7 => 28, 8 => 36];
        foreach ($expected as $n => $fame) {
            $cubes = [];
            for ($k = 0; $k < $n; $k++) {
                $cubes[] = $this->c(0, 3 * $k);
            }
            $r = FameCalculator::calculate([Fixtures::player(1, cubes: $cubes)], $board, [])[1];
            $this->assertSame($fame, $r->points('IV'), "{$n} regions");
            $this->assertSame($n, $r->points('III'), 'III: 海 1 つにつき 1');
        }
    }

    public function testLaunchPadCountsAsLinkedForRegionAndLandFame(): void
    {
        $pad = $this->c(1, 4);
        $board = Fixtures::board([$pad => AreaType::LAND]);
        $r = FameCalculator::calculate([Fixtures::player(1, cubes: [$this->c(0, 0)], pad: $pad)], $board, [])[1];
        $this->assertSame(3, $r->points('III')); // 海 1 + 陸 2
        $this->assertSame(3, $r->points('IV')); // A と B
    }

    public function testInactiveSatelliteFameIsNotCounted(): void
    {
        $board = Fixtures::board();
        $p = Fixtures::player(1, satellites: [
            Fixtures::sat(1, 'R001B', true),
            Fixtures::sat(2, 'R004B', false),
        ]);
        $r = FameCalculator::calculate([$p], $board, [])[1];
        $this->assertSame(5, $r->points('VI')); // 非稼働の CONCORDE 10 は 0（FAQ 行 65）
    }

    public function testProjectCardFameHalvesTheTotalOfInactiveSatellites(): void
    {
        $board = Fixtures::board();
        $p = Fixtures::player(1, satellites: [
            // 非稼働: 1+1+1 = 3（3 種そろう）、1+1 = 2 → 合計 5 の半分 = 2（カードごとに半分にすると 0）
            Fixtures::sat(1, 'R003B', false, ['P003', 'P041', 'P075']),
            Fixtures::sat(2, 'R005B', false, ['P010', 'P050']),
            // 稼働中: 全額（3 種はそろわない）
            Fixtures::sat(3, 'R001B', true, ['P017', 'P018']),
        ]);
        $r = FameCalculator::calculate([$p], $board, [])[1];
        $this->assertSame(['points' => 6 + 2 + 5, 'a' => 6, 'b' => 2, 'c' => 5], $r->detail['VII']);
    }

    public function testCompleteSatelliteBonusFiveEach(): void
    {
        $board = Fixtures::board();
        $p = Fixtures::player(1, satellites: [
            Fixtures::sat(1, 'R001B', true, ['P035', 'P010', 'P079']),   // 0 + 1 + 1
            Fixtures::sat(2, 'R002B', false, ['P035', 'P010', 'P079']),  // 非稼働でも 5
            Fixtures::sat(3, 'R003B', true, ['P001', 'P002']),            // CREW だけ
        ]);
        $r = FameCalculator::calculate([$p], $board, [])[1];
        $this->assertSame(10, $r->detail['VII']['c']);
    }

    public function testEndConditionBonusForEveryPlayerWhoMetIt(): void
    {
        $board = Fixtures::board();
        $r = FameCalculator::calculate([Fixtures::player(1, end: true), Fixtures::player(2, end: true), Fixtures::player(3)], $board, []);
        $this->assertSame([3, 3, 0], [$r[1]->points('IX'), $r[2]->points('IX'), $r[3]->points('IX')]);
        $this->assertSame(3, $r[1]->total);
    }

    public function testHandIsCashedOutAtThreeMayaEach(): void
    {
        $board = Fixtures::board();
        // 25 + 5 × 3 = 40 → 4
        $r = FameCalculator::calculate([Fixtures::player(1, maya: 25, hand: 5)], $board, [])[1];
        $this->assertSame(40, $r->detail['II']['maya']);
        $this->assertSame(4, $r->points('II'));
    }

    public function testHandCashOutAppliesActiveOrionRewardAndR009(): void
    {
        $board = Fixtures::board();
        $base = Fixtures::player(1, maya: 0, hand: 4);
        $this->assertSame(12, HandCashOut::mayaFor($base));

        // 稼働中の P077 で 1 枚 5 マヤ
        $orion = Fixtures::player(1, hand: 4, satellites: [Fixtures::sat(1, 'R001B', true, ['P077'])]);
        $this->assertSame(20, HandCashOut::mayaFor($orion));

        // P077 と R009（PHOBOS CONCORDE）は加算。1 枚 7 マヤ
        $both = Fixtures::player(1, hand: 4, satellites: [
            Fixtures::sat(1, 'R001B', true, ['P077']),
            Fixtures::sat(2, 'R009', true),
        ]);
        $this->assertSame(28, HandCashOut::mayaFor($both));

        // 非稼働の衛星では効かない
        $inactive = Fixtures::player(1, hand: 4, satellites: [
            Fixtures::sat(1, 'R001B', false, ['P077']),
            Fixtures::sat(2, 'R009', false),
        ]);
        $this->assertSame(12, HandCashOut::mayaFor($inactive));

        $r = FameCalculator::calculate([$both], $board, [])[1];
        $this->assertSame(2, $r->points('II'));
        $this->assertSame(28, $r->detail['II']['maya']);
    }

    public function testCashedOutMayaCountsForMayaHegemony(): void
    {
        $board = Fixtures::board();
        // 手札の換金後のマヤで T014 を比べる: 20 + 1 × 3 = 23 が 21 を上回る
        $players = [Fixtures::player(1, maya: 20, hand: 1), Fixtures::player(2, maya: 21)];
        $r = FameCalculator::calculate($players, $board, ['T014']);
        $this->assertSame(18, $r[1]->points('VIII'));
        $this->assertSame(12, $r[2]->points('VIII'));
    }

    public function testRewardWithZeroComparisonGetsNothing(): void
    {
        $board = Fixtures::board();
        // 陸区域の通信確立数: 誰も 0 なら 0、1 人だけ持っていれば 18 と 0
        $r = FameCalculator::calculate([Fixtures::player(1), Fixtures::player(2)], $board, ['T002']);
        $this->assertSame([0, 0], [$r[1]->points('VIII'), $r[2]->points('VIII')]);

        $pad = $this->c(1, 1);
        $board = Fixtures::board([$pad => AreaType::LAND]);
        $r = FameCalculator::calculate([Fixtures::player(1, pad: $pad), Fixtures::player(2)], $board, ['T002']);
        $this->assertSame([18, 0], [$r[1]->points('VIII'), $r[2]->points('VIII')]);
    }

    public function testDriveHegemonyTieExampleFromRulebookP24(): void
    {
        $board = Fixtures::board();
        // 駆動覇権で 4 人: 5, 3, 3, 3 → 18, 6, 6, 6（3 人が 2 位で並ぶ。RB p.24）
        $devices = [
            1 => ['P034', 'P035', 'P036', 'P037', 'P038'],
            2 => ['P039', 'P040', 'P042'],
            3 => ['P043', 'P045', 'P047'],
            4 => ['P048', 'P049', 'P052'],
        ];
        $players = [];
        foreach ($devices as $id => $cards) {
            $players[] = Fixtures::player($id, satellites: [
                Fixtures::sat($id * 10, 'R001B', true, array_slice($cards, 0, 3)),
                Fixtures::sat($id * 10 + 1, 'R002B', true, array_slice($cards, 3)),
            ]);
        }
        $r = FameCalculator::calculate($players, $board, ['T010']);
        $this->assertSame([18, 6, 6, 6], [$r[1]->points('VIII'), $r[2]->points('VIII'), $r[3]->points('VIII'), $r[4]->points('VIII')]);
    }
}
