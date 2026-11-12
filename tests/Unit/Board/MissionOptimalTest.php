<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Board;

use Bga\Games\projectedgeworth\Board\BoardGeometry;
use Bga\Games\projectedgeworth\Board\MissionCandidate;
use Bga\Games\projectedgeworth\Board\MissionMatcher;
use Bga\Games\projectedgeworth\Board\MissionTier;
use Bga\Games\projectedgeworth\Material\MissionCards;
use PHPUnit\Framework\TestCase;

/**
 * 2 枚のミッションの割り当てが、名声の合計の最大であること（追補 2026-11-12）。
 * 候補の列挙は MissionMatcher を使わず、002 のアキシャル座標を回転・平行移動して独立に求める。
 */
final class MissionOptimalTest extends TestCase
{
    /**
     * @param list<int> $owned
     * @return list<MissionCandidate>
     */
    private function enumerate(BoardGeometry $g, int $no, array $owned): array
    {
        $card = MissionCards::get(sprintf('M%03d', $no));
        $own = array_fill_keys($owned, true);
        $inOwn = static fn(array $ids): bool => array_diff_key(array_flip($ids), $own) === [];
        $out = [];
        $easy = $card->easyCells;
        $extra = $card->extraCells;
        for ($rot = 0; $rot < 6; $rot++) {
            for ($dq = -20; $dq <= 20; $dq++) {
                for ($dr = 0; $dr < 6; $dr++) {
                    $map = static function (array $cells) use ($g, $dq, $dr): ?array {
                        $ids = [];
                        foreach ($cells as [$q, $r]) {
                            $y = $r + $dr;
                            if ($y < 0 || $y >= BoardGeometry::ROWS) {
                                return null;
                            }
                            $ids[] = $g->cellId(2 * ($q + $dq) + $y + $g->parity(), $y);
                        }
                        return count(array_unique($ids)) === count($ids) ? $ids : null;
                    };
                    $e = $map($easy);
                    $x = $map($extra);
                    if ($e === null || !$inOwn($e)) {
                        continue;
                    }
                    sort($e);
                    $out['e' . implode(',', $e)] = new MissionCandidate($no, MissionTier::EASY, $card->fameEasy, $e);
                    if ($x !== null && $inOwn($x) && count(array_unique(array_merge($e, $x))) === count($e) + count($x)) {
                        $all = array_merge($e, $x);
                        sort($all);
                        $out['x' . implode(',', $all)] = new MissionCandidate($no, MissionTier::EXTRA, $card->fameFull, $all);
                    }
                }
            }
            $easy = array_map(static fn(array $c): array => [-$c[1], $c[0] + $c[1]], $easy);
            $extra = array_map(static fn(array $c): array => [-$c[1], $c[0] + $c[1]], $extra);
        }
        return array_values($out);
    }

    /** @param list<MissionCandidate> $list */
    private function bestOf(array $list, array $usedCells): ?MissionCandidate
    {
        $best = null;
        foreach ($list as $c) {
            if (array_intersect($c->cells, $usedCells) === [] && ($best === null || $c->fame > $best->fame)) {
                $best = $c;
            }
        }
        return $best;
    }

    /** @return iterable<string, array{int, int}> */
    public static function boards(): iterable
    {
        foreach ([[4, 0], [4, 1], [3, 0], [2, 1]] as [$b, $p]) {
            yield "B={$b} P={$p}" => [$b, $p];
        }
    }

    /** 独立に列挙した全組み合わせの最大と、assign の合計が一致する（ランダムな盤面）。 */
    #[\PHPUnit\Framework\Attributes\DataProvider('boards')]
    public function testAssignIsOptimal(int $b, int $p): void
    {
        $g = BoardGeometry::forBoardCount($b, $p);
        $rng = new SeededRandom(2026 + $b * 10 + $p);
        for ($t = 0; $t < 120; $t++) {
            $density = $rng->rand(30, 90);
            $owned = [];
            for ($c = 0; $c < $g->cellCount(); $c++) {
                if ($rng->rand(0, 99) < $density) {
                    $owned[] = $c;
                }
            }
            $m = $rng->rand(1, 10);
            $n = $rng->rand(1, 10);
            if ($m === $n) {
                continue;
            }
            $a = [null, ...$this->enumerate($g, $m, $owned)];
            $c2 = [null, ...$this->enumerate($g, $n, $owned)];
            $best = 0;
            foreach ($a as $x) {
                foreach ($c2 as $y) {
                    if ($x === null || $y === null || array_intersect($x->cells, $y->cells) === []) {
                        $best = max($best, ($x?->fame ?? 0) + ($y?->fame ?? 0));
                    }
                }
            }
            $res = MissionMatcher::assign($g, [$m, $n], $owned);
            self::assertSame($best, $res->totalFame, "{$m}/{$n} trial {$t}");
            // 採用した区域は重ならず、キューブのある区域だけ
            $used = [];
            foreach ($res->results as $cand) {
                foreach ($cand?->cells ?? [] as $cell) {
                    self::assertNotContains($cell, $used);
                    self::assertContains($cell, $owned);
                    $used[] = $cell;
                }
            }
        }
    }

    /**
     * 「貪欲（1 枚目の最高をまず取る）だと partial + partial、最適だと partial + complete」になる盤面が実在し、
     * assign は最適の方を返す。
     */
    public function testGreedyLosesToOptimal(): void
    {
        $g = BoardGeometry::forBoardCount(4, 0);
        $rng = new SeededRandom(7);
        $found = 0;
        for ($t = 0; $t < 400 && $found < 3; $t++) {
            $owned = [];
            for ($c = 0; $c < $g->cellCount(); $c++) {
                if ($rng->rand(0, 99) < 25) {
                    $owned[] = $c;
                }
            }
            $m = $rng->rand(1, 10);
            $n = $rng->rand(1, 10);
            if ($m === $n) {
                continue;
            }
            $ca = $this->enumerate($g, $m, $owned);
            $cb = $this->enumerate($g, $n, $owned);
            $first = $this->bestOf($ca, []);
            $second = $first === null ? null : $this->bestOf($cb, $first->cells);
            $greedyTiers = [$first?->tier, $second?->tier];
            $greedy = ($first?->fame ?? 0) + ($second?->fame ?? 0);
            $res = MissionMatcher::assign($g, [$m, $n], $owned);
            if ($res->totalFame <= $greedy) {
                continue;
            }
            // 貪欲より厳密に高い盤面
            $found++;
            self::assertGreaterThan($greedy, $res->totalFame);
            if ($greedyTiers === [MissionTier::EASY, MissionTier::EASY]) {
                $tiers = array_map(static fn(?MissionCandidate $c) => $c?->tier, $res->results);
                self::assertContains(MissionTier::EXTRA, $tiers, 'optimal has a complete mission');
            }
        }
        self::assertGreaterThanOrEqual(1, $found, 'no board where greedy < optimal was generated');
    }

    /** 最大 2×4 盤面（72 区域）で全探索が十分速い（最悪に近い、ほぼ全区域を所有）。 */
    public function testFastOnFullBoard(): void
    {
        $g = BoardGeometry::forBoardCount(4, 0);
        $owned = range(0, 71);
        $start = microtime(true);
        foreach ([[9, 10], [3, 4], [5, 6], [1, 2]] as [$m, $n]) {
            MissionMatcher::assign($g, [$m, $n], $owned);
        }
        self::assertLessThan(5.0, microtime(true) - $start);
    }
}
