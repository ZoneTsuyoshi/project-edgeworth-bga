<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * 効果の定義（011 §4-2）。1 カード 1 効果で、`cardKey`（`name_id`）をキーに `CardEffects` が持つ。不変のデータで、振る舞いは持たない。
 */
final readonly class EffectSpec
{
    /**
     * @param array<string, mixed> $params 部品ごとのパラメータ（011 §2、§7 の表）
     * @param list<EffectCondition> $conditions すべて満たすときだけトリガーされる（AND）
     * @param list<ResourceTouch> $touches 007 §6-2 の資源。007 §10 の付表の「資源」列と一致させる
     * @param int $stages 解決の段数（1 が標準。P087 は 2）
     */
    public function __construct(
        public string $cardKey,
        public Hook $hook,
        public EffectKind $kind,
        public bool $activeOnly,
        public bool $optional,
        public bool $selfOnly,
        public PartId $part,
        public array $params = [],
        public ?EffectCost $cost = null,
        public array $conditions = [],
        public array $touches = [],
        public int $stages = 1,
    ) {
    }

    /** 窓・ステップ内フックの結果部品（プールに積まれる効果）か */
    public function isTriggered(): bool
    {
        return $this->hook->kind() !== HookKind::Query && $this->part->category() === PartCategory::Result;
    }

    /** 問い合わせフックの修正・禁止か */
    public function isQuery(): bool
    {
        return $this->hook->kind() === HookKind::Query;
    }
}
