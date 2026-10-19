<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Query;

use Bga\Games\ProjectEdgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\ProjectEdgeworth\Flow\Action\StepMode;
use Bga\Games\ProjectEdgeworth\Material\ProjectType;

/**
 * 修正の問い合わせの文脈（011 §3-2）。どの数量を決める時点か（配備先の衛星、配備するカード、区域、モード）を持つ。
 */
final readonly class QueryContext
{
    public function __construct(
        public int $playerId,
        public ?int $satelliteId = null,
        public ?int $cardId = null,
        public ?ProjectType $cardType = null,
        public ?int $cell = null,
        public ?StepMode $mode = null,
        public ?ForbiddenAction $action = null,
        public int $attachedCount = 0,
    ) {
    }
}
