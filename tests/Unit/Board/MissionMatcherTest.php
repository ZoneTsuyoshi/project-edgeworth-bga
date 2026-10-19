<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Board;

use Bga\Games\ProjectEdgeworth\Board\BoardGeometry;
use Bga\Games\ProjectEdgeworth\Board\MissionMatcher;
use Bga\Games\ProjectEdgeworth\Board\MissionShape;
use Bga\Games\ProjectEdgeworth\Board\MissionShapes;
use Bga\Games\ProjectEdgeworth\Board\MissionTier;
use PHPUnit\Framework\TestCase;

final class MissionMatcherTest extends TestCase
{
    /** @return list<array{int, int}> 形の相対 [dx, dy]（最初の solid が原点） */
    private function rel(MissionShape $s, bool $hatched): array
    {
        $base = $s->solid[0];
        $cells = $hatched ? $s->hatched : $s->solid;
        return array_map(static fn(array $c): array => [$c[1] - $base[1], $c[0] - $base[0]], $cells);
    }

    /**
     * 60° 時計回り（画面上の実座標 (x, y√3) の回転で独立に導いた式。dx' = (dx - 3dy)/2、dy' = (dx + dy)/2）
     *
     * @param list<array{int, int}> $cells
     * @return list<array{int, int}>
     */
    private function rotate(array $cells): array
    {
        return array_map(static fn(array $c): array => [intdiv($c[0] - 3 * $c[1], 2), intdiv($c[0] + $c[1], 2)], $cells);
    }

    /**
     * @param list<array{int, int}> $cells
     * @return list<int>|null
     */
    private function place(BoardGeometry $g, array $cells, int $ax, int $ay): ?array
    {
        $ids = [];
        foreach ($cells as [$dx, $dy]) {
            if (!$g->inRows($ay + $dy)) {
                return null;
            }
            $ids[] = $g->cellId($ax + $dx, $ay + $dy);
        }
        if (count(array_unique($ids)) !== count($ids)) {
            return null;
        }
        sort($ids);
        return $ids;
    }

    /**
     * 形を盤面に置ける最初の位置を探して、solid と hatched の区域 ID を返す。
     *
     * @param list<array{int, int}> $solid
     * @param list<array{int, int}> $hatched
     * @return array{list<int>, list<int>}
     */
    private function findPlacement(BoardGeometry $g, array $solid, array $hatched, int $startCell = 0): array
    {
        for ($i = 0; $i < $g->cellCount(); $i++) {
            $c = $g->cellFromId(($startCell + $i) % $g->cellCount());
            $s = $this->place($g, $solid, $c->x, $c->y);
            $h = $this->place($g, $hatched, $c->x, $c->y);
            if ($s !== null && $h !== null) {
                return [$s, $h];
            }
        }
        self::fail('no placement');
    }

    /** @return iterable<string, array{int, int}> */
    public static function boards(): iterable
    {
        foreach ([[12, 0], [12, 1], [9, 0], [9, 1]] as [$w, $p]) {
            yield "W={$w} P={$p}" => [intdiv($w, 3), $p];
        }
    }

    /**
     * 回転 6 方向（003 §8.2）。10 枚すべて、各回転で易しい段階・追加段階を検出できる。
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('boards')]
    public function testSixRotations(int $b, int $p): void
    {
        $g = BoardGeometry::forBoardCount($b, $p);
        foreach (MissionShapes::all() as $shape) {
            $solid = $this->rel($shape, false);
            $hatched = $this->rel($shape, true);
            for ($rot = 0; $rot < 6; $rot++) {
                [$sIds, $hIds] = $this->findPlacement($g, $solid, $hatched, 7 * $rot);
                $all = array_merge($sIds, $hIds);
                sort($all);

                $extra = MissionMatcher::candidates($g, $shape, $all);
                $found = array_filter($extra, static fn($c) => $c->tier === MissionTier::EXTRA && $c->cells === $all);
                self::assertCount(1, $found, "{$shape->name} rot {$rot}: extra");
                self::assertSame($shape->fameExtra, array_values($found)[0]->fame);

                $easy = MissionMatcher::candidates($g, $shape, $sIds);
                self::assertNotEmpty($easy, "{$shape->name} rot {$rot}: easy");
                foreach ($easy as $c) {
                    self::assertSame(MissionTier::EASY, $c->tier);
                    self::assertSame($shape->fameEasy, $c->fame);
                }

                // 1 区域欠けると追加段階は成立しない
                $partial = MissionMatcher::candidates($g, $shape, array_slice($all, 1));
                self::assertEmpty(array_filter($partial, static fn($c) => $c->tier === MissionTier::EXTRA && $c->cells === $all));

                $solid = $this->rotate($solid);
                $hatched = $this->rotate($hatched);
            }
            // 6 回転で元に戻る
            self::assertSame($this->rel($shape, false), $solid);
        }
    }

    /**
     * 反転した形は成立しない。盤面の所有区域には solid / hatched の別がないので、
     * 反転した全体の区域集合が回転で重ならない（キラルな）形に限って検査する。
     */
    public function testMirrorDoesNotMatch(): void
    {
        $g = BoardGeometry::forBoardCount(4, 0);
        $chiral = 0;
        foreach (MissionShapes::all() as $shape) {
            $solid = $this->rel($shape, false);
            $hatched = $this->rel($shape, true);
            $mirror = static fn(array $cells): array => array_map(static fn(array $c): array => [-$c[0], $c[1]], $cells);
            $canon = static function (array $s, array $h): string {
                $f = static function (array $cells): array {
                    sort($cells);
                    return $cells;
                };
                // 平行移動を除く（最小の [dy, dx] を原点に）
                $all = array_merge($s, $h);
                usort($all, static fn($a, $b) => [$a[1], $a[0]] <=> [$b[1], $b[0]]);
                $o = $all[0];
                $n = static fn(array $cells): array => array_map(static fn(array $c): array => [$c[0] - $o[0], $c[1] - $o[1]], $cells);
                return json_encode([$f($n($s)), $f($n($h))], JSON_THROW_ON_ERROR);
            };
            $mirrorsToRotation = false;
            $rs = $solid;
            $rh = $hatched;
            for ($rot = 0; $rot < 6; $rot++) {
                if ($canon(array_merge($rs, $rh), []) === $canon(array_merge($mirror($solid), $mirror($hatched)), [])) {
                    $mirrorsToRotation = true;
                }
                $rs = $this->rotate($rs);
                $rh = $this->rotate($rh);
            }
            if ($mirrorsToRotation) {
                continue;
            }
            $chiral++;
            [$sIds, $hIds] = $this->findPlacement($g, $mirror($solid), $mirror($hatched));
            $all = array_merge($sIds, $hIds);
            $cands = MissionMatcher::candidates($g, $shape, $all);
            self::assertEmpty(array_filter($cands, static fn($c) => $c->tier === MissionTier::EXTRA), "{$shape->name} mirrored");
        }
        self::assertGreaterThanOrEqual(1, $chiral);
    }

    public function testLoopExampleFromRulebook(): void
    {
        // p.8: LOOP は solid のみで 5、solid + hatched で 17
        $g = BoardGeometry::forBoardCount(4, 0);
        $loop = MissionShapes::get(1);
        [$s, $h] = $this->findPlacement($g, $this->rel($loop, false), $this->rel($loop, true));
        self::assertSame(5, MissionMatcher::assign($g, [1], $s)->totalFame);
        self::assertSame(17, MissionMatcher::assign($g, [1], array_merge($s, $h))->totalFame);
        // 中央（空白の 1 マス）は何でもよい。他プレイヤーが置いていても関係ない（所有区域が増えても結果は同じ）
        self::assertGreaterThanOrEqual(17, MissionMatcher::assign($g, [1], range(0, 30))->totalFame);
        self::assertSame(0, MissionMatcher::assign($g, [1], [])->totalFame);
    }

    /** 左右の接続をまたぐ形（003 §8.5）。wrap = false で切り替えられる。 */
    public function testWrapAcrossLeftRightEdge(): void
    {
        foreach ([0, 1] as $p) {
            $g = BoardGeometry::forBoardCount(4, $p);
            $arch = MissionShapes::get(3);
            $solid = $this->rel($arch, false);
            $hatched = $this->rel($arch, true);
            // アンカーを右端の列（x が周期の端付近）に置く
            $anchor = $g->cellFromId(2 * 12 + 11);
            $s = $this->place($g, $solid, $anchor->x, $anchor->y);
            $h = $this->place($g, $hatched, $anchor->x, $anchor->y);
            self::assertNotNull($s);
            self::assertNotNull($h);
            $xs = array_map(static fn(array $c): int => $anchor->x + $c[0], array_merge($solid, $hatched));
            self::assertGreaterThanOrEqual($g->period(), max($xs), 'shape must cross the edge');
            $owned = array_merge($s, $h);
            $wrapped = MissionMatcher::assign($g, [3], $owned, true);
            self::assertSame(18, $wrapped->totalFame);
            // 接続しないなら、またぐ追加段階は成立せず、またがない易しい段階だけが残る
            $flat = MissionMatcher::assign($g, [3], $owned, false);
            self::assertSame(5, $flat->totalFame);
            self::assertSame(MissionTier::EASY, $flat->results[3]->tier);
        }
    }

    public function testTopBottomDoNotConnect(): void
    {
        // 縦に 3 段の ARCH solid を行 4・5・0 のように折り返して置いても成立しない
        $g = BoardGeometry::forBoardCount(4, 0);
        $owned = [$g->cellId(8, 4), $g->cellId(9, 5), $g->cellId(8, 0)];
        self::assertSame(0, MissionMatcher::assign($g, [3], $owned)->totalFame);
    }

    public function testTwoMissionsCannotShareCells(): void
    {
        $g = BoardGeometry::forBoardCount(4, 0);
        $lock = MissionShapes::get(9);
        [$s, $h] = $this->findPlacement($g, $this->rel($lock, false), $this->rel($lock, true));
        $owned = array_merge($s, $h);
        // 個別の最大: LOCK 24、TURTLE 易しい段階 5（同じ 3 連をそのまま使える）。合計 29 になってはならない
        $lockOnly = MissionMatcher::assign($g, [9], $owned);
        self::assertSame(24, $lockOnly->totalFame);
        $greedy = $lockOnly->totalFame + MissionMatcher::assign($g, [10], $owned)->totalFame;
        self::assertSame(29, $greedy);
        $both = MissionMatcher::assign($g, [9, 10], $owned);
        self::assertSame(24, $both->totalFame);
        self::assertNull($both->results[10]);
        self::assertSame(MissionTier::EXTRA, $both->results[9]->tier);
    }

    public function testTwoMissionsUseDisjointCells(): void
    {
        $g = BoardGeometry::forBoardCount(4, 0);
        $lock = MissionShapes::get(9);
        [$s, $h] = $this->findPlacement($g, $this->rel($lock, false), $this->rel($lock, true), 0);
        // LOCK の全体とは別の場所に TURTLE の易しい段階（一直線 3 つ）を足す
        $other = $this->findPlacement($g, $this->rel(MissionShapes::get(10), false), [], 40);
        $owned = array_values(array_unique(array_merge($s, $h, $other[0])));
        $both = MissionMatcher::assign($g, [9, 10], $owned);
        $cells = [];
        foreach ($both->results as $r) {
            if ($r !== null) {
                foreach ($r->cells as $c) {
                    self::assertNotContains($c, $cells, 'cell shared');
                    $cells[] = $c;
                }
            }
        }
        self::assertGreaterThanOrEqual(24 + 5, $both->totalFame);
    }

    /** RB p.22〜23 の例の再現: ARCH を 2 段階（18）、LOCK を 1 段階（5）で 23。盤面そのものは画像から起こしていない合成配置。 */
    public function testRulebookScoringExampleSynthetic(): void
    {
        $g = BoardGeometry::forBoardCount(4, 0);
        $arch = MissionShapes::get(3);
        $lock = MissionShapes::get(9);
        [$as, $ah] = $this->findPlacement($g, $this->rel($arch, false), $this->rel($arch, true), 0);
        // LOCK は易しい段階（一直線 3 つ）だけ、離れた位置に
        [$ls] = $this->findPlacement($g, $this->rel($lock, false), [], 50);
        $owned = array_values(array_unique(array_merge($as, $ah, $ls)));
        $res = MissionMatcher::assign($g, [3, 9], $owned);
        self::assertSame(23, $res->totalFame);
        self::assertSame(MissionTier::EXTRA, $res->results[3]->tier);
        self::assertSame(MissionTier::EASY, $res->results[9]->tier);
    }

    /** 全探索の検算: assign は候補の全組み合わせの最大と一致する。 */
    public function testAssignMatchesBruteForce(): void
    {
        $g = BoardGeometry::forBoardCount(2, 1);
        $rng = new SeededRandom(99);
        $pairs = [[9, 10], [3, 4], [5, 8], [1, 3], [6, 7]];
        foreach ($pairs as [$m, $n]) {
            for ($trial = 0; $trial < 15; $trial++) {
                $owned = [];
                for ($c = 0; $c < $g->cellCount(); $c++) {
                    if ($rng->rand(0, 99) < 70) {
                        $owned[] = $c;
                    }
                }
                $a = [null, ...MissionMatcher::candidates($g, MissionShapes::get($m), $owned)];
                $b = [null, ...MissionMatcher::candidates($g, MissionShapes::get($n), $owned)];
                $best = 0;
                foreach ($a as $x) {
                    foreach ($b as $y) {
                        if ($x !== null && $y !== null && array_intersect($x->cells, $y->cells) !== []) {
                            continue;
                        }
                        $best = max($best, ($x?->fame ?? 0) + ($y?->fame ?? 0));
                    }
                }
                self::assertSame($best, MissionMatcher::assign($g, [$m, $n], $owned)->totalFame, "{$m}/{$n} trial {$trial}");
            }
        }
    }

    public function testSameShapeTwiceCountsOnce(): void
    {
        $g = BoardGeometry::forBoardCount(4, 0);
        $arch = MissionShapes::get(3);
        [$s1] = $this->findPlacement($g, $this->rel($arch, false), [], 0);
        [$s2] = $this->findPlacement($g, $this->rel($arch, false), [], 40);
        self::assertSame(5, MissionMatcher::assign($g, [3], array_merge($s1, $s2))->totalFame);
    }
}
