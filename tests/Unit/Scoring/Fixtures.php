<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Scoring;

use Bga\Games\projectedgeworth\Board\AreaType;
use Bga\Games\projectedgeworth\Board\BoardGeometry;
use Bga\Games\projectedgeworth\Board\MissionShape;
use Bga\Games\projectedgeworth\Material\Region;
use Bga\Games\projectedgeworth\Scoring\LinkedCube;
use Bga\Games\projectedgeworth\Scoring\PlayerState;
use Bga\Games\projectedgeworth\Scoring\SatelliteState;
use Bga\Games\projectedgeworth\Scoring\ScoringBoard;

/**
 * テスト用の盤面（8 枚の地域ボード = 24 列 × 6 行、偶数行が左寄せ）とプレイヤーの組み立て。
 * 地域は列 3 つごとに A〜H。種類は指定がなければ標準海。
 */
final class Fixtures
{
    public const COLUMNS = 24;

    public static function geometry(): BoardGeometry
    {
        return BoardGeometry::forBoardCount(8, 0);
    }

    /** @param array<int, AreaType> $types 区域 ID => 種類（指定がない区域は標準海） */
    public static function board(array $types = []): ScoringBoard
    {
        $all = [];
        $regions = [];
        $cases = Region::cases();
        for ($id = 0; $id < self::COLUMNS * 6; $id++) {
            $all[$id] = $types[$id] ?? AreaType::SEA;
            $regions[$id] = $cases[intdiv($id % self::COLUMNS, 3)];
        }
        return new ScoringBoard(self::geometry(), $all, $regions);
    }

    public static function cell(int $row, int $col): int
    {
        return $row * self::COLUMNS + $col;
    }

    /**
     * ミッション形状を、基準マス（最初の薄いマス）が (ax, ay) になるよう回転なしで置いた区域 ID。
     *
     * @return list<int>
     */
    public static function place(MissionShape $shape, bool $withHatched, int $ax, int $ay): array
    {
        $g = self::geometry();
        $base = $shape->solid[0];
        $cells = $withHatched ? array_merge($shape->solid, $shape->hatched) : $shape->solid;
        $ids = [];
        foreach ($cells as $c) {
            $ids[] = $g->cellId($ax + $c[1] - $base[1], $ay + $c[0] - $base[0]);
        }
        sort($ids);
        return $ids;
    }

    /**
     * @param list<int|LinkedCube> $cubes 区域 ID（slot 0）または LinkedCube
     * @param list<SatelliteState> $satellites
     * @param list<int> $missions
     */
    public static function player(
        int $id,
        int $budget = 0,
        int $maya = 0,
        int $hand = 0,
        array $cubes = [],
        ?int $pad = null,
        array $satellites = [],
        array $missions = [],
        bool $end = false,
    ): PlayerState {
        $list = [];
        foreach ($cubes as $c) {
            $list[] = $c instanceof LinkedCube ? $c : new LinkedCube($c, 0);
        }
        return new PlayerState($id, $budget, $maya, $hand, $list, $pad, $satellites, $missions, $end);
    }

    /** @param list<string> $deployed */
    public static function sat(int $satId, string $cardId, bool $active, array $deployed = [], int $fuelUsed = 0): SatelliteState
    {
        return new SatelliteState($satId, $cardId, $active, $fuelUsed, $deployed);
    }
}
