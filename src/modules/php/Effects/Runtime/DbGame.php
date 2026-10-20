<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Runtime;

use Bga\Games\projectedgeworth\Board\Direction;
use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Data\FlowPhase;
use Bga\Games\projectedgeworth\Data\Satellite;
use Bga\Games\projectedgeworth\Effects\Context\EffectHost;
use Bga\Games\projectedgeworth\Effects\Context\GameMutator;
use Bga\Games\projectedgeworth\Effects\Context\GameView;
use Bga\Games\projectedgeworth\Effects\Context\SatelliteState;
use Bga\Games\projectedgeworth\Flow\Action\ActionServices;
use Bga\Games\projectedgeworth\Flow\FuelMath;
use Bga\Games\projectedgeworth\Material\ActionKind;
use Bga\Games\projectedgeworth\Material\ProjectCards;
use Bga\Games\projectedgeworth\Material\ProjectType;
use Bga\Games\projectedgeworth\Material\SatelliteCards;

/**
 * 効果エンジンの `GameView` / `GameMutator` の本番実装（`Data/` のリポジトリを使う。`tests/Support/Effects/FakeGame` と同じ契約）。
 *
 * - `ActionServices` は `attach()` で後から結ぶ（`ActionServices` が `ActionHooks` を要り、`ActionHooks` が `GameView` を要るため）。
 * - 書き込みは `journal` に記録する。窓の中の結果の通知（N8・N10・N12・N14・N15・N17・N19・N29）は、この記録から組み立てる
 *   （`EffectNotices`。変更後の値がその時点のものになる）。
 */
final class DbGame implements GameView, GameMutator
{
    private ?ActionServices $services = null;

    /** @var list<array<string, mixed>> 書き込みの記録（時系列）。キー `t` が種類 */
    private array $journal = [];

    public function attach(ActionServices $services): void
    {
        $this->services = $services;
    }

    public function s(): ActionServices
    {
        return $this->services ?? throw new \LogicException('DbGame is not attached to the action services');
    }

    // ---- 記録 ----

    public function journalMark(): int
    {
        return count($this->journal);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function journalSince(int $mark): array
    {
        return array_slice($this->journal, $mark);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function note(array $entry): void
    {
        $this->journal[] = $entry;
    }

    // ---- GameView ----

    public function playerIds(): array
    {
        return $this->s()->players->playerIds();
    }

    public function hosts(int $playerId): array
    {
        $hosts = [];
        foreach ($this->s()->satellites->forPlayer($playerId) as $sat) {
            $hosts[] = $this->satHost($sat);
            foreach ($this->s()->cards->deployedIds($sat->id) as $i => $cardId) {
                $hosts[] = $this->cardHost($cardId, $sat, $i + 1);
            }
        }

        return $hosts;
    }

    public function host(string $kind, int $src): ?EffectHost
    {
        if ($kind === 'S') {
            try {
                return $this->satHost($this->s()->satellites->get($src));
            } catch (\InvalidArgumentException) {
                return null;
            }
        }
        try {
            $at = $this->s()->cards->locationOf($src);
        } catch (\InvalidArgumentException) {
            return null;
        }
        if ($at['location'] !== CardLocation::Satellite) {
            return null;
        }
        $sat = $this->s()->satellites->get($at['arg']);
        $order = array_search($src, $this->s()->cards->deployedIds($sat->id), true);

        return $this->cardHost($src, $sat, ($order === false ? 0 : $order) + 1);
    }

    public function maya(int $playerId): int
    {
        return $this->s()->players->maya($playerId);
    }

    public function hand(int $playerId): array
    {
        return $this->s()->cards->idsIn(CardLocation::Hand, $playerId);
    }

    public function sellPrice(int $playerId): int
    {
        return $this->s()->selling->price($playerId);
    }

    public function deviceCountOn(int $satelliteId): int
    {
        $count = 0;
        foreach ($this->s()->cards->deployedIds($satelliteId) as $cardId) {
            if (self::project($cardId)->type === ProjectType::Device) {
                $count++;
            }
        }

        return $count;
    }

    public function isProposalPhase(): bool
    {
        return $this->s()->globals->flowPhase() === FlowPhase::Proposal;
    }

    public function isOwnLinkedArea(int $playerId, int $cell): bool
    {
        return $this->s()->links->hasLink($playerId, $cell) || $this->s()->players->padCell($playerId) === $cell;
    }

    public function cardsOn(int $satelliteId): int
    {
        return $this->s()->cards->countIn(CardLocation::Satellite, $satelliteId);
    }

    public function declaredBonus(int $playerId, string $action): bool
    {
        $declared = $this->s()->globals->flowContext()['declared'] ?? [];

        return in_array($playerId, array_map('intval', (array) ($declared[$action] ?? [])), true);
    }

    public function canRecoverFuel(int $satelliteId): bool
    {
        $sat = $this->s()->satellites->get($satelliteId);

        return $sat->isActive() && $sat->fuelUsed >= 1 && $sat->fuelUsed <= $this->s()->fuelCapacity($satelliteId);
    }

    public function satelliteIds(int $playerId): array
    {
        return array_map(static fn(Satellite $s): int => $s->id, $this->s()->satellites->forPlayer($playerId));
    }

    public function satelliteState(int $satelliteId): ?SatelliteState
    {
        try {
            $sat = $this->s()->satellites->get($satelliteId);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return new SatelliteState(
            $sat->id,
            $sat->playerId,
            $sat->shape,
            $sat->cell,
            $sat->direction,
            $sat->isActive() ? $sat->fuelUsed : 0,
            $this->s()->fuelCapacity($sat->id),
        );
    }

    public function deployedCards(int $satelliteId): array
    {
        return $this->s()->cards->deployedIds($satelliteId);
    }

    public function linkedCells(int $playerId): array
    {
        return $this->s()->links->cellsFor($playerId);
    }

    public function pioneerRecipients(int $playerId, int $cell): array
    {
        $ids = [];
        foreach ($this->s()->links->inCell($cell) as $link) {
            if ($link->playerId !== $playerId) {
                $ids[] = $link->playerId;
            }
        }
        foreach ($this->s()->players->padCells() as $owner => $padCell) {
            if ($padCell === $cell && $owner !== $playerId) {
                $ids[] = $owner;
            }
        }

        return $this->s()->seatOrder($ids);
    }

    // ---- GameMutator ----

    public function markUsed(EffectHost $host): void
    {
        if ($host->kind === 'S') {
            $this->s()->satellites->setActivation($host->src, 1);
        } else {
            $this->s()->cards->setActivation($host->src, 1);
        }
    }

    public function payMaya(int $playerId, int $amount): void
    {
        $this->changeMaya($playerId, -$amount, 'cost');
    }

    public function sellCards(int $playerId, array $cardIds): void
    {
        if ($cardIds === []) {
            return;
        }
        $price = $this->s()->selling->price($playerId);
        $this->s()->cards->discard($cardIds);
        $this->note(['t' => 'discard', 'p' => $playerId, 'cards' => $cardIds, 'reason' => 'sell']);
        $this->changeMaya($playerId, $price * count($cardIds), 'sell');
    }

    public function gainMaya(int $playerId, int $amount): void
    {
        $this->changeMaya($playerId, $amount, 'effect');
    }

    public function changeBudget(int $playerId, int $amount): void
    {
        $after = $this->s()->players->addBudget($playerId, $amount);
        $this->note(['t' => 'budget', 'p' => $playerId, 'delta' => $amount, 'budget' => $after]);
    }

    public function drawCards(int $playerId, int $count): int
    {
        $before = $this->s()->cards->reshuffles();
        $ids = $this->s()->cards->drawTop($playerId, $count);
        $this->note([
            't' => 'draw',
            'p' => $playerId,
            'cards' => $ids,
            'reshuffled' => $this->s()->cards->reshuffles() > $before,
            'zone' => $this->isProposalPhase() ? 'hand' : 'hand',
        ]);

        return count($ids);
    }

    public function recoverFuel(int $satelliteId, int $amount): int
    {
        $sat = $this->s()->satellites->get($satelliteId);
        $capacity = $this->s()->fuelCapacity($satelliteId);
        if (!$sat->isActive() || FuelMath::isOutOfFuel($sat->fuelUsed, $capacity)) {
            return 0;
        }
        $after = FuelMath::change($sat->fuelUsed, -$amount, $capacity);
        $this->writeFuel($sat, $after, $capacity);

        return $sat->fuelUsed - $after;
    }

    public function returnPlottedActionToHand(int $playerId, int $actionNo): void
    {
        $this->s()->actionCards->returnToHand($playerId, $actionNo);
        $this->note(['t' => 'actionKept', 'p' => $playerId, 'action' => $actionNo]);
    }

    public function discardFromHand(int $playerId, array $cardIds): void
    {
        if ($cardIds === []) {
            return;
        }
        $this->s()->cards->discard($cardIds);
        $this->note(['t' => 'discard', 'p' => $playerId, 'cards' => $cardIds, 'reason' => 'effect']);
    }

    public function declareBonus(int $playerId, string $action): void
    {
        $actionNo = self::actionNumber($action);
        $globals = $this->s()->globals;
        $holders = $globals->bonusHolders();
        $list = $holders[$actionNo] ?? [];
        if (!in_array($playerId, $list, true)) {
            $list[] = $playerId;
            $holders[$actionNo] = $this->s()->seatOrder($list);
            ksort($holders);
            $globals->setBonusHolders($holders);
        }
        $context = $globals->flowContext();
        $declared = (array) ($context['declared'][$action] ?? []);
        $declared[] = $playerId;
        $context['declared'][$action] = array_values(array_unique(array_map('intval', $declared)));
        $globals->setFlowContext($context);
        $this->note(['t' => 'bonus', 'p' => $playerId, 'action' => $actionNo]);
    }

    public function spendFuel(int $satelliteId, int $amount): int
    {
        $sat = $this->s()->satellites->get($satelliteId);
        $capacity = $this->s()->fuelCapacity($satelliteId);
        if (!$sat->isActive() || FuelMath::isOutOfFuel($sat->fuelUsed, $capacity)) {
            return 0;
        }
        $after = FuelMath::change($sat->fuelUsed, $amount, $capacity);
        $this->writeFuel($sat, $after, $capacity);

        return $after - $sat->fuelUsed;
    }

    public function setFuelUsed(int $satelliteId, int $used): void
    {
        $sat = $this->s()->satellites->get($satelliteId);
        if (!$sat->isActive()) {
            return;
        }
        $this->writeFuel($sat, $used, $this->s()->fuelCapacity($satelliteId));
    }

    public function moveSatellite(int $satelliteId, int $cell, Direction $direction): void
    {
        $this->s()->satellites->move($satelliteId, $cell, $direction);
        $this->note(['t' => 'sat', 'sat' => $satelliteId]);
    }

    public function turnSatellite(int $satelliteId, Direction $direction): void
    {
        $sat = $this->s()->satellites->get($satelliteId);
        $this->s()->satellites->turn($satelliteId, $direction);
        $this->note(['t' => 'turn', 'p' => $sat->playerId, 'sat' => $satelliteId, 'dir' => $direction->value, 'same' => $sat->direction === $direction]);
        $this->note(['t' => 'sat', 'sat' => $satelliteId]);
    }

    public function swapSatellites(int $satelliteIdA, int $satelliteIdB): void
    {
        $this->s()->satellites->swapPositions($satelliteIdA, $satelliteIdB);
        $this->note(['t' => 'sat', 'sat' => $satelliteIdA]);
        $this->note(['t' => 'sat', 'sat' => $satelliteIdB]);
    }

    public function deployFromHand(int $playerId, int $cardNo, int $satelliteId): void
    {
        $tier = $this->s()->cards->deploy($playerId, $cardNo, $satelliteId);
        $this->note(['t' => 'deploy', 'p' => $playerId, 'card' => $cardNo, 'sat' => $satelliteId, 'tier' => $tier, 'hand' => true]);
        $this->note(['t' => 'sat', 'sat' => $satelliteId]);
    }

    public function deployFromDeck(int $playerId, int $satelliteId): ?int
    {
        $before = $this->s()->cards->reshuffles();
        $ids = $this->s()->cards->drawTop($playerId, 1);
        if ($ids === []) {
            return null;
        }
        $tier = $this->s()->cards->deploy($playerId, $ids[0], $satelliteId);
        $this->note([
            't' => 'deploy', 'p' => $playerId, 'card' => $ids[0], 'sat' => $satelliteId, 'tier' => $tier, 'hand' => false,
            'reshuffled' => $this->s()->cards->reshuffles() > $before,
        ]);
        $this->note(['t' => 'sat', 'sat' => $satelliteId]);

        return $ids[0];
    }

    public function discardDeployed(int $cardNo): void
    {
        $at = $this->s()->cards->locationOf($cardNo);
        $sat = $this->s()->satellites->get($at['arg']);
        $this->s()->cards->undeploy($cardNo, CardLocation::Discard);
        $this->note(['t' => 'discard', 'p' => $sat->playerId, 'cards' => [$cardNo], 'reason' => 'effect']);
        $this->note(['t' => 'sat', 'sat' => $sat->id]);
    }

    public function rearrangeDeployed(array $placement): void
    {
        $this->s()->cards->arrange($placement);
        foreach (array_keys($placement) as $satId) {
            $this->note(['t' => 'sat', 'sat' => $satId]);
        }
    }

    // ---- 内部 ----

    private function changeMaya(int $playerId, int $delta, string $reason): void
    {
        if ($delta === 0) {
            return;
        }
        $after = $this->s()->players->addMaya($playerId, $delta);
        $this->note(['t' => 'maya', 'p' => $playerId, 'delta' => $delta, 'maya' => $after, 'reason' => $reason]);
    }

    private function writeFuel(Satellite $sat, int $used, int $capacity): void
    {
        if ($used === $sat->fuelUsed) {
            return;
        }
        $after = $this->s()->satellites->addFuelUsed($sat->id, $used - $sat->fuelUsed, $capacity);
        $this->note(['t' => 'fuel', 'p' => $sat->playerId, 'sat' => $sat->id, 'delta' => $after - $sat->fuelUsed, 'used' => $after, 'capacity' => $capacity]);
        $this->note(['t' => 'sat', 'sat' => $sat->id]);
    }

    private function satHost(Satellite $sat): EffectHost
    {
        return new EffectHost(
            'S',
            $sat->id,
            SatelliteCards::get($sat->cardId)->nameId,
            $sat->playerId,
            $sat->id,
            $sat->shape,
            0,
            $sat->isActive() || $sat->grace,
            $sat->activation !== 0,
        );
    }

    private function cardHost(int $cardId, Satellite $sat, int $order): EffectHost
    {
        return new EffectHost(
            'P',
            $cardId,
            self::project($cardId)->nameId,
            $sat->playerId,
            $sat->id,
            $sat->shape,
            $order,
            $sat->isActive() || $sat->grace,
            $this->s()->cards->activation($cardId) !== 0,
        );
    }

    private static function project(int $cardNo): \Bga\Games\projectedgeworth\Material\ProjectCardDefinition
    {
        return ProjectCards::get(sprintf('P%03d', $cardNo));
    }

    /** `ActionKind` の値 => アクション番号（I〜V = 1〜5） */
    public static function actionNumber(string $action): int
    {
        return match (ActionKind::from($action)) {
            ActionKind::Budget => 1,
            ActionKind::Deploy => 2,
            ActionKind::LaunchTurn => 3,
            ActionKind::Link => 4,
            ActionKind::Proposal => 5,
        };
    }
}
