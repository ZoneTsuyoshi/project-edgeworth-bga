<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Query;

use Bga\Games\ProjectEdgeworth\Data\Satellite;
use Bga\Games\ProjectEdgeworth\Effects\Context\EffectHost;
use Bga\Games\ProjectEdgeworth\Effects\Context\GameView;
use Bga\Games\ProjectEdgeworth\Flow\Action\ActionHooks;
use Bga\Games\ProjectEdgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\ProjectEdgeworth\Flow\Action\ProposalCounts;
use Bga\Games\ProjectEdgeworth\Flow\Action\StepMode;
use Bga\Games\ProjectEdgeworth\Material\ActionKind;
use Bga\Games\ProjectEdgeworth\Material\ProjectCards;

/**
 * 効果エンジンにつないだ `ActionHooks`（`NoActionHooks` の差し替え。011 §3-2）。
 *
 * 起動（`ACTIVATED`）の修正（P019〜P021、P036、R021〜R025）は、[Use] を選んだものだけが働く。`ActionHooks` の各メソッドは
 * 選択を受け取れないので、配備・打ち上げ・通信確立のステップの検証の前に `useMods()` で使うカードの鍵を渡す（011 §6-3）。
 * 使用済みを付けるのは、そのコストを実際に払うコミットの側（`GameMutator::markUsed()`）。
 */
final class EngineActionHooks implements ActionHooks
{
    /** @var list<string> */
    private array $useMods = [];

    /**
     * @param GameView|null $view 渡すと、P036 の「打ち上げる衛星に配備されているカードの枚数」と、R011 の「自分で選んだボーナスか」
     *                            （EUROPA の宣言で得たボーナスなら偽。K12、FAQ 行 71）を結ぶ。省略時は 0 枚・自分で選んだ扱い
     */
    public function __construct(private readonly EffectQueries $queries, private readonly ?GameView $view = null)
    {
    }

    /**
     * 以後の問い合わせで使う起動の修正の宿主の鍵（`EffectHost::key()`）。空配列で解除する。
     *
     * @param list<string> $hostKeys
     */
    public function useMods(array $hostKeys): void
    {
        $this->useMods = $hostKeys;
    }

    public function deployCostReduction(int $playerId, Satellite $target, int $cardNumber, int $attachedCount): int
    {
        $card = ProjectCards::get(sprintf('P%03d', $cardNumber));
        $query = new QueryContext($playerId, $target->id, $cardNumber, $card->type, attachedCount: $attachedCount);
        $self = new EffectHost('P', $cardNumber, $card->nameId, $playerId, $target->id, $target->shape, 0, $target->isActive(), false);

        return $this->queries->deployCost($query, 0, $this->useMods, $self)->reduction();
    }

    public function launchCostReduction(int $playerId, Satellite $satellite): int
    {
        $attached = $this->view?->cardsOn($satellite->id) ?? 0;

        return $this->queries->launchCost(new QueryContext($playerId, $satellite->id, attachedCount: $attached), 0, $this->useMods)->reduction();
    }

    public function extraLinkTargets(int $playerId, Satellite $satellite, StepMode $mode): array
    {
        return $this->queries->linkTargets(new QueryContext($playerId, $satellite->id, cell: $satellite->cell, mode: $mode, direction: $satellite->direction), [], $this->useMods);
    }

    public function pioneerWaived(int $playerId, Satellite $satellite): bool
    {
        return $this->queries->pioneerWaived(new QueryContext($playerId, $satellite->id));
    }

    public function linkCostReduction(int $playerId, Satellite $satellite, int $cell): int
    {
        return $this->queries->linkCost(new QueryContext($playerId, $satellite->id, cell: $cell), 0, $this->useMods)->reduction();
    }

    public function isForbidden(ForbiddenAction $action, Satellite $satellite): bool
    {
        return !$this->queries->allowed(new QueryContext($satellite->playerId, $satellite->id, action: $action));
    }

    public function proposalCounts(int $playerId, bool $bonus, ProposalCounts $base): ProposalCounts
    {
        $mode = $bonus ? StepMode::Bonus : StepMode::Main;

        // 宣言（R016）で得たボーナスでは、R011 は効かない（K12。FAQ 行 71）
        $own = $bonus && $this->view !== null ? !$this->view->declaredBonus($playerId, ActionKind::Proposal->value) : null;

        return $this->queries->proposalCounts(new QueryContext($playerId, mode: $mode, ownActionChoice: $own), $base);
    }

    public function budgetMainGain(int $playerId, int $base): int
    {
        return $this->queries->budgetMainGain(new QueryContext($playerId, mode: StepMode::Main), $base);
    }

    public function sellPrice(int $playerId, int $base): int
    {
        return $this->queries->sellPrice(new QueryContext($playerId), $base);
    }
}
