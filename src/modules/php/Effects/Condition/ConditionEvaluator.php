<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Condition;

use Bga\Games\ProjectEdgeworth\Material\ConditionId;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;

/**
 * 条件 K01〜K13 の評価（011 §2-6）。`EffectSpec::$conditions` はすべて満たすときだけ成立する（AND）。
 * E-0 では K01・K02 だけを実装している。他の条件は E-1 以降でハンドラを登録する。未登録の条件を評価すると例外にする
 * （黙って成立・不成立にすると、効果が静かに誤動作するため）。
 */
final class ConditionEvaluator
{
    /** @var array<string, ConditionHandler> */
    private array $handlers = [];

    public function __construct()
    {
        $this->register(ConditionId::EventSatellite, new EventSatelliteCondition());
        $this->register(ConditionId::EventCardType, new EventCardTypeCondition());
    }

    public function register(ConditionId $id, ConditionHandler $handler): void
    {
        $this->handlers[$id->value] = $handler;
    }

    public function holds(EffectSpec $spec, ConditionContext $context): bool
    {
        foreach ($spec->conditions as $condition) {
            $handler = $this->handlers[$condition->id->value]
                ?? throw new \LogicException("condition {$condition->id->value} is not implemented yet");
            if (!$handler->holds($condition, $context)) {
                return false;
            }
        }

        return true;
    }
}
