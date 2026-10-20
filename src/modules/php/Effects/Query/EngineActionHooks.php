<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Query;

use Bga\Games\projectedgeworth\Data\Satellite;
use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Context\GameView;
use Bga\Games\projectedgeworth\Flow\Action\ActionHooks;
use Bga\Games\projectedgeworth\Flow\Action\ForbiddenAction;
use Bga\Games\projectedgeworth\Effects\EffectViolation;
use Bga\Games\projectedgeworth\Flow\Action\ProposalCounts;
use Bga\Games\projectedgeworth\Flow\Action\RuleViolation;
use Bga\Games\projectedgeworth\Flow\Action\StepMode;
use Bga\Games\projectedgeworth\Material\ActionKind;
use Bga\Games\projectedgeworth\Material\ProjectCards;

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
     * @param ProposalOptions|null $proposal 渡すと、企画の残すカードの選択のオプション（P068・P070・P072）を結ぶ。省略時はオプションなし
     * @param GameView|null $view 渡すと、P036 の「打ち上げる衛星に配備されているカードの枚数」と、R011 の「自分で選んだボーナスか」
     *                            （EUROPA の宣言で得たボーナスなら偽。K12、FAQ 行 71）を結ぶ。省略時は 0 枚・自分で選んだ扱い
     */
    public function __construct(
        private readonly EffectQueries $queries,
        private readonly ?GameView $view = null,
        private readonly ?ProposalOptions $proposal = null,
    ) {
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

    public function validateProposal(int $playerId, bool $bonus, ProposalCounts $counts, array $drawn, array $keep, array $data): void
    {
        if ($this->proposal === null) {
            $need = min(count($drawn), $counts->keep);
            if (count($keep) !== $need) {
                throw new RuleViolation("Keep exactly $need card(s)");
            }

            return;
        }
        $plan = $this->proposal->plan($playerId, $bonus ? StepMode::Bonus : StepMode::Main);
        try {
            $this->proposal->validate(
                $playerId,
                $plan,
                $counts->keep,
                $drawn,
                $keep,
                self::ids($data['handDiscard'] ?? []),
                (array) ($data['optionUses'] ?? []),
                self::ids($data['sell'] ?? []),
            );
        } catch (EffectViolation $violation) {
            throw new RuleViolation($violation->getMessage());
        }
    }

    public function applyProposal(int $playerId, bool $bonus, array $data): void
    {
        if ($this->proposal === null) {
            return;
        }
        $plan = $this->proposal->plan($playerId, $bonus ? StepMode::Bonus : StepMode::Main);
        $this->proposal->apply(
            $playerId,
            $plan,
            self::ids($data['handDiscard'] ?? []),
            (array) ($data['optionUses'] ?? []),
            self::ids($data['sell'] ?? []),
        );
    }

    public function proposalExtras(int $playerId, bool $bonus, array $drawn): array
    {
        if ($this->proposal === null) {
            return [];
        }
        $plan = $this->proposal->plan($playerId, $bonus ? StepMode::Bonus : StepMode::Main);
        if (!$plan->handDiscard && $plan->options === []) {
            return [];
        }

        return $this->proposal->describe($playerId, $plan, $drawn);
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        return array_values(array_map('intval', (array) $value));
    }
}
