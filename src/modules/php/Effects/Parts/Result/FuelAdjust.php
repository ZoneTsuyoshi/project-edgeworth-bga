<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Result;

use Bga\Games\projectedgeworth\Effects\Context\EffectContext;
use Bga\Games\projectedgeworth\Flow\FuelMath;

/**
 * 配備・除去・付け替えで燃料庫の数 C が変わるときの、消費量 c の調整（申し送り N-1、001 §4-4 の U-6）。
 * 燃料庫の外に出ている衛星（燃料切れ）に配備して C が増えても、燃料切れのまま（c = C + 1 に直す）。`GameMutator` は c を調整しないので、
 * 配備・除去・付け替えを行う部品がこのクラスで包む。
 */
final class FuelAdjust
{
    private function __construct()
    {
    }

    /**
     * `$change` を実行し、その前後の燃料庫の数に合わせて稼働中の衛星の消費量を `FuelMath::afterCapacityChange()` で直す。
     *
     * @param \Closure(): mixed $change
     */
    public static function around(EffectContext $ctx, int $satelliteId, \Closure $change): mixed
    {
        return self::aroundMany($ctx, [$satelliteId], $change);
    }

    /**
     * 複数の衛星のカード構成を一度に変える操作（付け替え）の前後で、それぞれの衛星の消費量を直す。
     *
     * @param list<int> $satelliteIds
     * @param \Closure(): mixed $change
     */
    public static function aroundMany(EffectContext $ctx, array $satelliteIds, \Closure $change): mixed
    {
        $before = [];
        foreach ($satelliteIds as $id) {
            $before[$id] = $ctx->view->satelliteState($id);
        }
        $result = $change();
        foreach ($before as $id => $old) {
            $new = $ctx->view->satelliteState($id);
            if ($old === null || $new === null || !$new->isActive()) {
                continue;
            }
            $used = FuelMath::afterCapacityChange($old->fuelUsed, $old->capacity, $new->capacity);
            if ($used !== $new->fuelUsed) {
                $ctx->mutator->setFuelUsed($id, $used);
            }
        }

        return $result;
    }
}
