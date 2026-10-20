<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Board;

/**
 * ミッション形状の一致判定と 2 枚の割り当て最適化（003 §8）。
 * 回転は 6 方向、反転しない。区域の種類は問わない。
 */
final class MissionMatcher
{
    private function __construct()
    {
    }

    /**
     * 達成候補をすべて返す。
     *
     * @param list<int> $ownedCells 自分のキューブ・発射拠点がある区域 ID の集合 P
     * @param bool $wrap 形が左右の接続をまたいでよいか（003 §8.5、未確定 No.4 の暫定は true）
     * @return list<MissionCandidate>
     */
    public static function candidates(
        BoardGeometry $geometry,
        MissionShape $shape,
        array $ownedCells,
        bool $wrap = true,
    ): array {
        $owned = array_fill_keys($ownedCells, true);
        $base = $shape->solid[0];
        $toRel = static fn(array $c): array => [$c[1] - $base[1], $c[0] - $base[0]]; // [dx, dy]
        $solid = array_map($toRel, $shape->solid);
        $hatched = array_map($toRel, $shape->hatched);

        $found = [];
        for ($rot = 0; $rot < 6; $rot++) {
            foreach ($ownedCells as $p) {
                $pc = $geometry->cellFromId($p);
                $solidIds = self::place($geometry, $solid, $pc->x, $pc->y, $wrap);
                if ($solidIds === null || !self::allOwned($solidIds, $owned)) {
                    continue;
                }
                $tiers = [[MissionTier::EASY, $shape->fameEasy, $solidIds]];
                $hatchedIds = self::place($geometry, $hatched, $pc->x, $pc->y, $wrap);
                if ($hatchedIds !== null && self::allOwned($hatchedIds, $owned)) {
                    $all = array_merge($solidIds, $hatchedIds);
                    if (count(array_unique($all)) === count($all)) {
                        $tiers[] = [MissionTier::EXTRA, $shape->fameExtra, $all];
                    }
                }
                foreach ($tiers as [$tier, $fame, $ids]) {
                    sort($ids);
                    $found[$tier->value . ':' . implode(',', $ids)] = new MissionCandidate($shape->id, $tier, $fame, $ids);
                }
            }
            $solid = array_map(self::rotate(...), $solid);
            $hatched = array_map(self::rotate(...), $hatched);
        }
        return array_values($found);
    }

    /**
     * 2 枚（以下）のミッションについて、同じ区域を共有しない名声合計が最大の組み合わせを返す。
     *
     * @param list<int> $missionIds 1〜2 枚のミッション番号
     * @param list<int> $ownedCells
     */
    public static function assign(BoardGeometry $geometry, array $missionIds, array $ownedCells, bool $wrap = true): MissionAssignment
    {
        $options = [];
        foreach ($missionIds as $id) {
            $list = self::candidates($geometry, MissionShapes::get($id), $ownedCells, $wrap);
            $list[] = null; // 達成しない
            $options[$id] = $list;
        }

        $best = null;
        self::search(array_keys($options), 0, $options, [], [], 0, $best);
        /** @var array{fame: int, cells: int, results: array<int, MissionCandidate|null>} $best */
        return new MissionAssignment($best['fame'], $best['results']);
    }

    /**
     * @param list<int> $ids
     * @param array<int, list<MissionCandidate|null>> $options
     * @param array<int, bool> $used
     * @param array<int, MissionCandidate|null> $chosen
     * @param array{fame: int, cells: int, results: array<int, MissionCandidate|null>}|null $best
     */
    private static function search(array $ids, int $i, array $options, array $used, array $chosen, int $fame, ?array &$best): void
    {
        if ($i === count($ids)) {
            $cells = count($used);
            if ($best === null || $fame > $best['fame'] || ($fame === $best['fame'] && $cells < $best['cells'])) {
                $best = ['fame' => $fame, 'cells' => $cells, 'results' => $chosen];
            }
            return;
        }
        $id = $ids[$i];
        foreach ($options[$id] as $cand) {
            if ($cand === null) {
                self::search($ids, $i + 1, $options, $used, $chosen + [$id => null], $fame, $best);
                continue;
            }
            $conflict = false;
            foreach ($cand->cells as $c) {
                if (isset($used[$c])) {
                    $conflict = true;
                    break;
                }
            }
            if ($conflict) {
                continue;
            }
            $newUsed = $used;
            foreach ($cand->cells as $c) {
                $newUsed[$c] = true;
            }
            self::search($ids, $i + 1, $options, $newUsed, $chosen + [$id => $cand], $fame + $cand->fame, $best);
        }
    }

    /** 60° 時計回り（相対 [dx, dy]、003 §1.3） */
    private static function rotate(array $c): array
    {
        [$dx, $dy] = $c;
        $q = intdiv($dx - $dy, 2);
        $r = $dy;
        return [$q - $r, $q + $r];
    }

    /**
     * @param list<array{0: int, 1: int}> $rel 相対 [dx, dy]
     * @return list<int>|null 区域 ID。盤面外・重複があれば null
     */
    private static function place(BoardGeometry $geometry, array $rel, int $ax, int $ay, bool $wrap): ?array
    {
        $ids = [];
        foreach ($rel as [$dx, $dy]) {
            $x = $ax + $dx;
            $y = $ay + $dy;
            if (!$geometry->inRows($y) || (!$wrap && ($x < 0 || $x >= $geometry->period()))) {
                return null;
            }
            $ids[] = $geometry->cellId($x, $y);
        }
        return count(array_unique($ids)) === count($ids) ? $ids : null;
    }

    /**
     * @param list<int> $ids
     * @param array<int, bool> $owned
     */
    private static function allOwned(array $ids, array $owned): bool
    {
        foreach ($ids as $id) {
            if (!isset($owned[$id])) {
                return false;
            }
        }
        return true;
    }
}
