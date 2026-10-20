<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Condition;

use Bga\Games\projectedgeworth\Effects\Context\GameView;
use Bga\Games\projectedgeworth\Material\ProjectCards;
use Bga\Games\projectedgeworth\Material\ProjectType;

/**
 * 衛星に配備されたプロジェクトカードのタイプの集計（K03 `FULL_TYPES`、P008 が使う）。`GameView::hosts()` の配備されたカード（kind 'P'）から数える。
 */
final class SatelliteTypes
{
    private function __construct()
    {
    }

    /**
     * CREW・DEVICE・SOFTWARE がすべて配備されているか。
     */
    public static function isFull(GameView $view, int $playerId, int $satelliteId): bool
    {
        return in_array($satelliteId, self::fullSatellites($view, $playerId), true);
    }

    /**
     * CREW・DEVICE・SOFTWARE がすべて配備されているプレイヤーの衛星（稼働の有無は問わない）。
     *
     * @return list<int> 衛星 ID
     */
    public static function fullSatellites(GameView $view, int $playerId): array
    {
        $types = [];
        foreach ($view->hosts($playerId) as $host) {
            if ($host->kind === 'P' && $host->satelliteId !== null) {
                $types[$host->satelliteId][ProjectCards::get(sprintf('P%03d', $host->src))->type->value] = true;
            }
        }
        $full = [];
        foreach ($types as $satelliteId => $found) {
            if (count($found) === count(ProjectType::cases())) {
                $full[] = $satelliteId;
            }
        }
        sort($full);

        return $full;
    }
}
