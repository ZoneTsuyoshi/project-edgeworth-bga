<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Condition;

use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameEvent;
use Bga\Games\ProjectEdgeworth\Effects\Query\QueryContext;
use Bga\Games\ProjectEdgeworth\Material\ProjectType;

/**
 * 条件の評価に使う入力（011 §2-6）: 効果を持つカード（宿主）と、フックのイベントまたは問い合わせの文脈のどちらか。
 */
final readonly class ConditionContext
{
    public function __construct(
        public EffectHost $host,
        public ?GameEvent $event = null,
        public ?QueryContext $query = null,
    ) {
    }

    /** イベントまたは問い合わせの衛星 */
    public function satelliteId(): ?int
    {
        return $this->event?->satelliteId ?? $this->query?->satelliteId;
    }

    /** 配備されたカード（またはこれから配備するカード）のタイプ */
    public function cardType(): ?ProjectType
    {
        return $this->event?->cardType ?? $this->query?->cardType;
    }
}
