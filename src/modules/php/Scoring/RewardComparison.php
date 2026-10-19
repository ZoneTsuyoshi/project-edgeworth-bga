<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Scoring;

use Bga\Games\ProjectEdgeworth\Board\AreaType;
use Bga\Games\ProjectEdgeworth\Material\ProjectType;
use Bga\Games\ProjectEdgeworth\Material\Region;

/**
 * 褒賞カード 24 面（T001〜T024）の「比較内容」の値（002 §7、FAQ 行 79〜87）。
 * ゲーム中（プレイヤーパネルの表示）にもゲーム終了時（褒賞の順位）にも使う。
 * 通信確立済み区域の集合 P は、キューブの区域 + 自分の発射拠点の区域（FAQ 行 79）。
 */
final class RewardComparison
{
    private function __construct()
    {
    }

    /**
     * @param list<int> $allLaunchPadCells 全プレイヤーの発射拠点の区域 ID（T008 用）
     */
    public static function value(string $rewardId, PlayerState $player, ScoringBoard $board, array $allLaunchPadCells = []): int
    {
        $p = $player->linkedCells();
        return match ($rewardId) {
            'T001' => self::countCells($board, $p, static fn(AreaType $t): bool => $t->isSea()),
            'T002' => self::countCells($board, $p, static fn(AreaType $t): bool => $t->isLand()),
            'T003' => count($board->components($p)),
            'T004' => self::largestGroup($board, $p),
            'T005' => self::countRegions($board, $p, [Region::A, Region::C, Region::E, Region::G]),
            'T006' => self::countRegions($board, $p, [Region::B, Region::D, Region::F, Region::H]),
            'T007' => self::notFirst($player),
            'T008' => self::nextToLaunchPad($board, $player, $p, $allLaunchPadCells),
            'T009' => self::deployedOfType($player, ProjectType::Crew),
            'T010' => self::deployedOfType($player, ProjectType::Device),
            'T011' => self::deployedOfType($player, ProjectType::Software),
            'T012' => self::completeSatellites($player),
            'T013' => $player->budget,
            'T014' => $player->maya,
            'T015' => self::activeFuel($player),
            'T016' => self::maxFuelCapacity($player),
            'T017' => self::countCells($board, $p, static fn(AreaType $t): bool => $t === AreaType::LAND || $t === AreaType::FOREST),
            'T018' => self::countCells($board, $p, static fn(AreaType $t): bool => $t === AreaType::SEA),
            'T019' => self::adjacentTo($board, $p, AreaType::MOUNTAIN, true),
            'T020' => self::adjacentTo($board, $p, AreaType::SNOW, true),
            'T021' => self::adjacentTo($board, $p, AreaType::FOREST, true),
            'T022' => self::mostAreaTypes($board, $p),
            'T023' => self::adjacentTo($board, $p, AreaType::DEEP_SEA, true),
            'T024' => self::adjacentTo($board, $p, AreaType::REEF, false),
            default => throw new \OutOfBoundsException("未定義の褒賞カード ID: {$rewardId}"),
        };
    }

    /**
     * @param list<int> $cells
     * @param callable(AreaType): bool $filter
     */
    private static function countCells(ScoringBoard $board, array $cells, callable $filter): int
    {
        $n = 0;
        foreach ($cells as $id) {
            if ($filter($board->areaType($id))) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * @param list<int> $cells
     * @param list<Region> $regions
     */
    private static function countRegions(ScoringBoard $board, array $cells, array $regions): int
    {
        $n = 0;
        foreach ($cells as $id) {
            if (in_array($board->region($id), $regions, true)) {
                $n++;
            }
        }
        return $n;
    }

    /** @param list<int> $cells */
    private static function largestGroup(ScoringBoard $board, array $cells): int
    {
        $max = 0;
        foreach ($board->components($cells) as $group) {
            $max = max($max, count($group));
        }
        return $max;
    }

    /** T007: 自分のキューブが区域の 1 番目（slot 0）以外にある区域の数（FAQ 行 82）。発射拠点は数えない */
    private static function notFirst(PlayerState $player): int
    {
        $n = 0;
        foreach ($player->cubes as $cube) {
            if ($cube->slot > 0) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * T008: いずれかの発射拠点に隣接する区域のうち、自分が通信確立済みの数。自分の発射拠点の区域そのものは数えない（FAQ 行 83）。
     *
     * @param list<int> $cells
     * @param list<int> $allLaunchPadCells
     */
    private static function nextToLaunchPad(ScoringBoard $board, PlayerState $player, array $cells, array $allLaunchPadCells): int
    {
        $adjacent = [];
        foreach ($allLaunchPadCells as $pad) {
            foreach ($board->neighbors($pad) as $n) {
                $adjacent[$n] = true;
            }
        }
        $n = 0;
        foreach ($cells as $id) {
            if ($id !== $player->launchPadCell && isset($adjacent[$id])) {
                $n++;
            }
        }
        return $n;
    }

    /** T009〜T011: 配備されているプロジェクトカードの数（非稼働の衛星も数える。FAQ 行 84） */
    private static function deployedOfType(PlayerState $player, ProjectType $type): int
    {
        $n = 0;
        foreach ($player->satellites as $sat) {
            $n += $sat->deployedCount($type);
        }
        return $n;
    }

    /** T012: CREW・DEVICE・SOFTWARE がそろった衛星の数（非稼働も数える。FAQ 行 84） */
    public static function completeSatellites(PlayerState $player): int
    {
        $n = 0;
        foreach ($player->satellites as $sat) {
            if ($sat->hasAllTypes()) {
                $n++;
            }
        }
        return $n;
    }

    /** T015: 稼働中の衛星の残燃料の合計（庫外は 0。FAQ 行 85） */
    private static function activeFuel(PlayerState $player): int
    {
        $sum = 0;
        foreach ($player->satellites as $sat) {
            if ($sat->active) {
                $sum += $sat->remainingFuel();
            }
        }
        return $sum;
    }

    /** T016: 燃料庫の数が最大の 1 機の値（非稼働も対象。FAQ 行 86） */
    private static function maxFuelCapacity(PlayerState $player): int
    {
        $max = 0;
        foreach ($player->satellites as $sat) {
            $max = max($max, $sat->fuelCapacity());
        }
        return $max;
    }

    /**
     * T019〜T021・T023・T024: 指定の種類の区域に隣接する、通信確立済みの海区域（または陸区域）の数。
     * 隣接元の区域自体の通信確立は不要で、複数に隣接しても 1 回（FAQ 行 87）。
     *
     * @param list<int> $cells
     */
    private static function adjacentTo(ScoringBoard $board, array $cells, AreaType $neighborType, bool $sea): int
    {
        $n = 0;
        foreach ($cells as $id) {
            $type = $board->areaType($id);
            if ($sea ? !$type->isSea() : !$type->isLand()) {
                continue;
            }
            foreach ($board->neighbors($id) as $nb) {
                if ($board->areaType($nb) === $neighborType) {
                    $n++;
                    break;
                }
            }
        }
        return $n;
    }

    /** T022: 連続した（つながった）通信確立済み区域のまとまりの中の、区域の種類（7 種）の数の最大 */
    private static function mostAreaTypes(ScoringBoard $board, array $cells): int
    {
        $max = 0;
        foreach ($board->components($cells) as $group) {
            $types = [];
            foreach ($group as $id) {
                $types[$board->areaType($id)->value] = true;
            }
            $max = max($max, count($types));
        }
        return $max;
    }
}
