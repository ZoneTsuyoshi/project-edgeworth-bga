<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Question;

/**
 * 問いのフィールド 1 つ（011 §6-1）。`key` が答え（`Answer::$fields`）の鍵になる。
 * `min`・`max` は `Cards`・`Pairs`・`UseMods` では選べる個数、`Amount` では候補が無いときの整数の範囲。
 */
final readonly class Field
{
    /**
     * @param list<mixed> $candidates 候補（サーバーが絞り込み済み。`Assignment` は部品が検証するので空でよい）
     */
    public function __construct(
        public string $key,
        public FieldType $type,
        public array $candidates = [],
        public int $min = 0,
        public int $max = 0,
        public mixed $default = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'min' => $this->min,
            'max' => $this->max,
            'candidates' => $this->candidates,
            'default' => $this->default,
        ];
    }
}
