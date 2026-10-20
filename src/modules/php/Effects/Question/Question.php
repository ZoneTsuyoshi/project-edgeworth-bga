<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Question;

/**
 * 窓の「問い」（011 §6-1、状態 31 の `getArgs` の `_private[playerId].questions` の 1 要素）。
 * 問いは保存せず、プールと `Effects/` から `getArgs` のたびに組み立てる（009 §6.2）。
 */
final readonly class Question
{
    /**
     * @param list<array{entryId: int, cardKey: string, forced: bool}> $entries ORDER: 順序を選ぶ集合（強制の効果も含める）
     * @param list<int> $defaultOrder ORDER: デフォルトの順（007 §6-3）
     * @param array<string, mixed> $cost EFFECT: 代価の表示（kind / amount / tiers / canPay / shortfall / canPayBySelling）
     * @param list<Field> $fields EFFECT: 中身の選択
     * @param array<string, mixed> $preview EFFECT: 結果の見込み（サーバーが計算する。008 §2-6）
     */
    public function __construct(
        public QuestionType $type,
        public array $entries = [],
        public array $defaultOrder = [],
        public ?int $entryId = null,
        public ?string $cardKey = null,
        public bool $mandatory = false,
        public array $cost = [],
        public array $fields = [],
        public array $preview = [],
    ) {
    }

    /**
     * @param list<array{entryId: int, cardKey: string, forced: bool}> $entries
     * @param list<int> $defaultOrder
     */
    public static function order(array $entries, array $defaultOrder): self
    {
        return new self(QuestionType::Order, $entries, $defaultOrder);
    }

    /**
     * @param array<string, mixed> $cost
     * @param list<Field> $fields
     * @param array<string, mixed> $preview
     */
    public static function effect(
        int $entryId,
        string $cardKey,
        bool $mandatory,
        array $cost,
        array $fields,
        array $preview = [],
    ): self {
        return new self(QuestionType::Effect, [], [], $entryId, $cardKey, $mandatory, $cost, $fields, $preview);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->type === QuestionType::Order) {
            return ['type' => 'ORDER', 'entries' => $this->entries, 'defaultOrder' => $this->defaultOrder];
        }

        return [
            'type' => 'EFFECT',
            'entryId' => $this->entryId,
            'cardKey' => $this->cardKey,
            'mandatory' => $this->mandatory,
            'cost' => $this->cost,
            'fields' => array_map(static fn(Field $f): array => $f->toArray(), $this->fields),
            'preview' => $this->preview,
        ];
    }
}
