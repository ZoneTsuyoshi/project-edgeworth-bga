<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Question;

/**
 * 効果の問いへの答え（`actUseEffect(entryId, fields, sell)` の `fields` と `sell`。011 §6-1）。
 */
final readonly class Answer
{
    /**
     * @param array<string, mixed> $fields 問いのフィールドの `key` => 値
     * @param list<int> $sell 売却する手札のカード ID（空でよい。008 §2-6 の同梱）
     */
    public function __construct(
        public array $fields = [],
        public array $sell = [],
    ) {
    }
}
