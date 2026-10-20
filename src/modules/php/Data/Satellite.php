<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Data;

use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Material\SatelliteColor;
use Bga\Games\projectedgeworth\Material\SatellitePlan;
use Bga\Games\projectedgeworth\Material\SatelliteShape;

/**
 * satellite テーブルの 1 行（010 §5.1）。稼働中かどうかは cell が null かどうかが唯一の正。
 */
final class Satellite
{
    public function __construct(
        public readonly int $id,
        public readonly int $playerId,
        public readonly SatelliteColor $color,
        public readonly SatelliteShape $shape,
        public readonly SatellitePlan $plan,
        public readonly string $cardId,
        public readonly ?int $cell,
        public readonly ?Direction $direction,
        public readonly int $fuelUsed,
        public readonly bool $grace,
        public readonly int $activation,
    ) {
    }

    public function isActive(): bool
    {
        return $this->cell !== null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['sat_id'],
            (int) $row['sat_player'],
            SatelliteRepository::colorFromInt((int) $row['sat_color']),
            SatelliteRepository::shapeFromInt((int) $row['sat_shape']),
            SatelliteRepository::planFromInt((int) $row['sat_plan']),
            (string) $row['sat_card'],
            $row['sat_cell'] === null ? null : (int) $row['sat_cell'],
            $row['sat_dir'] === null ? null : Direction::from((string) $row['sat_dir']),
            (int) $row['sat_fuel_used'],
            (int) $row['sat_grace'] === 1,
            (int) $row['sat_activation'],
        );
    }
}
