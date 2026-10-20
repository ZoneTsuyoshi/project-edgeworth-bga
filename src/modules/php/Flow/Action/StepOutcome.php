<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

/**
 * 1 人分の選択を処理した結果。公開してよい内容かどうかは details のキーごとに状態クラス側が決める
 * （引いたカードの ID などは本人にだけ通知する）。
 */
final class StepOutcome
{
    /**
     * @param string $op 実際に行った操作（deploy / draw / launch / turn / budget / link / keep / plot / pass）
     * @param bool $paymentFailed コストを払えず [Pass] として処理した（008 の暫定 No.5。売却もしない）
     * @param array<string, mixed> $details 操作ごとの結果
     */
    public function __construct(
        public readonly int $playerId,
        public readonly string $op,
        public readonly bool $paymentFailed = false,
        public readonly array $details = [],
    ) {
    }

    public function isPass(): bool
    {
        return $this->op === 'pass';
    }
}
