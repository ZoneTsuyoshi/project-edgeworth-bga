<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts\Modifier;

use Bga\Games\ProjectEdgeworth\Board\BoardLayout;
use Bga\Games\ProjectEdgeworth\Board\Direction;
use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Parts\ModifierPart;
use Bga\Games\ProjectEdgeworth\Effects\Query\QueryContext;
use Bga\Games\ProjectEdgeworth\Flow\Action\StepMode;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;

/**
 * M04 `LINK_TARGET_EXPAND`: 通信確立の対象区域の集合に区域を足す（和集合。011 §2-3、007 §5-4 の手順 1）。
 * 特殊通信確立・効果による通信確立にも常に効く（FAQ 行 24・25）。このカードのある衛星の通信確立だけに働く。
 *
 * パラメータ:
 * - `shape`: `ADJACENT_LAND`（直下に隣接する陸）/ `ADJACENT_SEA`（隣接する海）/ `ALL_ADJACENT6`（隣接する 6 区域すべて）/
 *   `RIGHT2`・`LEFT2`（進行方向の右側・左側の隣接 2 区域）/ `FRONT3`・`BACK3`（進行方向の前方・後方の隣接 3 区域）。
 * - `bonusOnly`: 真ならアクション選択ボーナスのときだけ（R014）。
 *
 * 位置は `QueryContext` の `cell`（衛星の直下）と `direction` から求める。隣接は 003 §6.2（左右は接続、上下は範囲外を除く）。
 * 右・左・前・後は反射を考慮しない（FAQ 行 26・27・36、003 §6.1）。盤面外の区域は含めない。
 */
final class LinkTargetExpand implements ModifierPart
{
    /** 時計回りの並び（`Direction::rotateClockwise()` と同じ） */
    private const CLOCKWISE = [Direction::E, Direction::SE, Direction::SW, Direction::W, Direction::NW, Direction::NE];

    public function __construct(private readonly BoardLayout $layout)
    {
    }

    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        if ($query->satelliteId === null || $query->satelliteId !== $host->satelliteId || $query->cell === null) {
            return false;
        }
        if (($spec->params['bonusOnly'] ?? false) === true && $query->mode !== StepMode::Bonus) {
            return false;
        }

        return true;
    }

    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
    {
        if (!is_array($value) || $query->cell === null) {
            throw new \InvalidArgumentException('LINK_TARGET_EXPAND folds a list of cell ids');
        }
        $geometry = $this->layout->geometry();
        $here = $geometry->cellFromId($query->cell);
        $shape = (string) ($spec->params['shape'] ?? '');
        $added = [];
        foreach ($this->directions($shape, $query->direction) as $direction) {
            $y = $here->y + $direction->dy();
            if (!$geometry->inRows($y)) {
                continue;
            }
            $id = $geometry->cellId($here->x + $direction->dx(), $y);
            $type = $this->layout->areaType($id);
            $keep = match ($shape) {
                'ADJACENT_LAND' => $type->isLand(),
                'ADJACENT_SEA' => $type->isSea(),
                default => true,
            };
            if ($keep) {
                $added[] = $id;
            }
        }

        return array_values(array_unique([...$value, ...$added]));
    }

    /**
     * @return list<Direction> 直下から見た隣接の方向
     */
    private function directions(string $shape, ?Direction $heading): array
    {
        if ($shape === 'ADJACENT_LAND' || $shape === 'ADJACENT_SEA' || $shape === 'ALL_ADJACENT6') {
            return self::CLOCKWISE;
        }
        if ($heading === null) {
            return [];
        }
        $i = array_search($heading, self::CLOCKWISE, true);
        if ($i === false) {
            return [];
        }
        // 進行方向 h の周りを時計回りに数える。前方 = h-1, h, h+1、後方 = h+2, h+3, h+4、右側 = h+1, h+2、左側 = h-1, h-2
        $offsets = match ($shape) {
            'FRONT3' => [-1, 0, 1],
            'BACK3' => [2, 3, 4],
            'RIGHT2' => [1, 2],
            'LEFT2' => [-1, -2],
            default => throw new \LogicException('LINK_TARGET_EXPAND needs a known shape'),
        };

        return array_map(static fn(int $o): Direction => self::CLOCKWISE[(($i + $o) % 6 + 6) % 6], $offsets);
    }
}
