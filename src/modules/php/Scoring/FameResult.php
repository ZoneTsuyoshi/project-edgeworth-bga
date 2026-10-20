<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Scoring;

/**
 * 1 人の最終名声と内訳（010 §11.2 の fame_detail の形式）。
 */
final class FameResult
{
    /**
     * @param array<string, mixed> $detail 区分 I〜IX の内訳（total を含む）
     */
    public function __construct(
        public readonly int $playerId,
        public readonly int $total,
        public readonly array $detail,
    ) {
    }

    /** @return int 区分（'I'〜'IX'）の名声 */
    public function points(string $section): int
    {
        /** @var array{points: int} $s */
        $s = $this->detail[$section];
        return $s['points'];
    }

    public function toJson(): string
    {
        return json_encode($this->detail, JSON_THROW_ON_ERROR);
    }
}
