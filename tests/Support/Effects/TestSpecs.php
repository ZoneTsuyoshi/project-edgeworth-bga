<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support\Effects;

use Bga\Games\projectedgeworth\Material\EffectCondition;
use Bga\Games\projectedgeworth\Material\EffectCost;
use Bga\Games\projectedgeworth\Material\EffectKind;
use Bga\Games\projectedgeworth\Material\EffectSpec;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\PartId;
use Bga\Games\projectedgeworth\Material\ResourceTouch;

/**
 * テスト用の疑似カードの `EffectSpec` を短く組み立てる。
 */
final class TestSpecs
{
    /**
     * @param array<string, mixed> $params
     * @param list<EffectCondition> $conditions
     * @param list<ResourceTouch> $touches
     */
    public static function spec(
        string $key,
        Hook $hook = Hook::BudgetStart,
        PartId $part = PartId::GainMaya,
        EffectKind $kind = EffectKind::Constant,
        bool $optional = false,
        bool $activeOnly = false,
        bool $selfOnly = false,
        array $params = [],
        ?EffectCost $cost = null,
        array $conditions = [],
        array $touches = [],
        int $stages = 1,
    ): EffectSpec {
        return new EffectSpec($key, $hook, $kind, $activeOnly, $optional, $selfOnly, $part, $params, $cost, $conditions, $touches, $stages);
    }
}
