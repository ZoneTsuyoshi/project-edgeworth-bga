<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Query;

use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Material\ProjectType;

/**
 * 修正の問い合わせの文脈（011 §3-2）。どの数量を決める時点か（配備先の衛星、配備するカード、区域、モード）を持つ。
 *
 * - `cell`: 問い合わせの区域。対象区域の問い合わせ（`LINK_TARGETS`）と循環フェイズの問い合わせ（`FUEL_CONSUME`・`NEAR_MISS`）では
 *   衛星の直下の区域、通信確立コストでは通信確立する区域。
 * - `direction`: 衛星の向き（`LINK_TARGETS` の右・左・前・後の拡張が使う）。
 * - `ownActionChoice`: そのアクション選択ボーナスを自分でそのアクションを選んで得ているか（K12。R016〜R020 の宣言で得たなら偽）。
 *   null は「自分で選んだ」として扱う（`EngineActionHooks` が EUROPA の宣言を `GameView::declaredBonus()` で結ぶ）。
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
        public ?Direction $direction = null,
        public ?bool $ownActionChoice = null,
    ) {
    }
}
