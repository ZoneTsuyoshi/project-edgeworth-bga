<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow;

/** ゲーム終了判定（001 §10）と進行度（009 §10）。純粋関数。 */
final class EndCondition
{
    public const SATELLITES = 5;
    public const CUBES = 16;
    /** 進行度の衛星側の重み（着陸で戻りうるので下げる。009 §10、暫定案どおり） */
    public const SATELLITE_WEIGHT = 0.8;

    private function __construct()
    {
    }

    /**
     * 終了条件を満たしたプレイヤーの ID（席順）。(i) 衛星が 5 機とも稼働中、(ii) 通信確立数が 16。
     * 両方満たしても +3（名声 IX）は 1 回なので 1 人 1 件。空ならゲーム続行。
     *
     * @param list<PlayerProgress> $players
     * @return list<int>
     */
    public static function metBy(array $players): array
    {
        $ids = [];
        foreach ($players as $p) {
            if ($p->activeSatellites >= self::SATELLITES || $p->links >= self::CUBES) {
                $ids[] = $p->playerId;
            }
        }
        return $ids;
    }

    /**
     * ゲーム進行度 0〜99（009 §10）。$highWater 以上に保つ（単調増加）。
     *
     * @param list<PlayerProgress> $players
     */
    public static function progress(array $players, int $highWater): int
    {
        $cube = 0.0;
        $sat = 0.0;
        foreach ($players as $p) {
            $cube = max($cube, $p->links / self::CUBES);
            $sat = max($sat, $p->activeSatellites / self::SATELLITES);
        }
        $raw = max($cube, self::SATELLITE_WEIGHT * $sat);
        return max(min(99, (int) floor(100 * $raw + 1e-9)), $highWater);
    }
}
