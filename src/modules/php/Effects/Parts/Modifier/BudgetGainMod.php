<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts\Modifier;

use Bga\Games\projectedgeworth\Effects\Condition\SatelliteTypes;
use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Context\GameView;
use Bga\Games\projectedgeworth\Effects\Parts\ModifierPart;
use Bga\Games\projectedgeworth\Effects\Query\QueryContext;
use Bga\Games\projectedgeworth\Material\EffectSpec;

/**
 * M09 `BUDGET_GAIN_MOD`: 予算発行のメインで得るマヤに足す（011 §2-3）。ボーナスの +6 には足さない（007 未確定 2。呼び出し側が
 * メインのときだけ問い合わせる）。
 *
 * パラメータ: `perFullSatellite`（CREW・DEVICE・SOFTWARE が揃っている自分の衛星 1 機につき +。P008）。
 * 揃っているかは衛星の稼働の有無を問わず数える（002 の効果文に稼働の条件がないため）。
 */
final class BudgetGainMod implements ModifierPart
{
    public function __construct(private readonly GameView $view)
    {
    }

    public function appliesTo(QueryContext $query, EffectSpec $spec, EffectHost $host): bool
    {
        return $host->playerId === $query->playerId;
    }

    public function modify(QueryContext $query, EffectSpec $spec, EffectHost $host, mixed $value): mixed
    {
        $full = count(SatelliteTypes::fullSatellites($this->view, $query->playerId));

        return (int) $value + (int) ($spec->params['perFullSatellite'] ?? 0) * $full;
    }
}
