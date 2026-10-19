<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow\Action;

use Bga\Games\ProjectEdgeworth\Data\Satellite;

/**
 * カード効果が数量・候補を変えるための問い合わせ口（011 §3-2 の問い合わせフックのうち I〜V と売却に関わるもの）。
 * 効果エンジン（I-12 以降）が実装を差し替える。既定は NoActionHooks（修正なし・禁止なし）。
 *
 * - コストの修正はすべて「引く量」（0 以上）で返す。下限 0 は呼び出し側が最後に 1 回だけ適用する（007 §5-2）。
 * - 修正同士は加算で重なる（007 §5-1）。複数の効果の合計は実装側で足してから返す。
 * - 起動の修正（[Use] した分）を含めるかどうかは実装側の責任。ここでは合計だけを受け取る。
 */
interface ActionHooks
{
    /**
     * `DEPLOY_COST`: 配備コストから引く量。$attachedCount は配備前に対象の衛星に付いているカードの枚数（P026 用）。
     */
    public function deployCostReduction(int $playerId, Satellite $target, int $cardNumber, int $attachedCount): int;

    /**
     * `LAUNCH_COST`: 打ち上げコストから引く量。衛星は非稼働のまま評価する（FAQ 行 67）。
     */
    public function launchCostReduction(int $playerId, Satellite $satellite): int;

    /**
     * `LINK_TARGETS`: 通信確立の対象に加える区域 ID（和集合。特殊通信確立にも効く。FAQ 行 24・25）。
     *
     * @return list<int>
     */
    public function extraLinkTargets(int $playerId, Satellite $satellite, StepMode $mode): array;

    /**
     * `LINK_PIONEER`: 他プレイヤーへの先駆コストを 0 にするか（P055。区域コストは必要。FAQ 行 57）。
     */
    public function pioneerWaived(int $playerId, Satellite $satellite): bool;

    /**
     * `LINK_COST`: 通信確立コスト（区域コスト + 先駆コスト）から引く量。区域コストから先に引く（007 §5-4、未確定 7）。
     */
    public function linkCostReduction(int $playerId, Satellite $satellite, int $cell): int;

    /**
     * `ALLOWED`: 禁止効果でこの衛星にその行動を認めないか。
     */
    public function isForbidden(ForbiddenAction $action, Satellite $satellite): bool;

    /**
     * `PROPOSAL_COUNTS`: 企画で引く枚数・残す枚数。$base は基本値（メイン 2 → 1、ボーナス 5 → 2）。
     */
    public function proposalCounts(int $playerId, bool $bonus, ProposalCounts $base): ProposalCounts;

    /**
     * `BUDGET_MAIN_GAIN`: 予算発行のメインで得るマヤ。$base は予算の値。ボーナスの +6 には働かない（007 未確定 2）。
     */
    public function budgetMainGain(int $playerId, int $base): int;

    /**
     * `SELL_PRICE`: 手札 1 枚の売却額。$base は 3。企画・効果で捨てるカードには適用しない（FAQ 行 31・60・70）。
     */
    public function sellPrice(int $playerId, int $base): int;
}
