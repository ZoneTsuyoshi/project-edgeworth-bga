<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Action;

use Bga\Games\projectedgeworth\Board\Adjacency;
use Bga\Games\projectedgeworth\Board\AreaType;
use Bga\Games\projectedgeworth\Data\LinkRepository;
use Bga\Games\projectedgeworth\Data\Satellite;

/**
 * IV 通信確立（001 §7-5・§8、008 §3-4・§3-5、009 の 80〜82）。
 *
 * choice_data（010 §9.3）: {"op": "link", "cell": 区域, "sat": sat_id, "sell": [ID, …]} / {"op": "pass"}（キューブの放棄。Q-10）
 * メインとボーナスで同じ形。違いは対象区域だけ（メイン = 衛星の直下、ボーナス = 直下と進行方向の前後。反射は考慮しない）。
 *
 * 通信確立可能区域 = (i) 対象区域、(ii) 自分の発射拠点もキューブもない、(iii) 払える。キューブが 16 個を使い切っていれば選べない。
 * 1 回で 1 区域のみ。特殊通信確立は cost(special: true) で特殊コスト 5 を足す（効果エンジンが LinkRequest を作って LinkCommitter に渡す）。
 */
final class LinkPhase implements ActionStep
{
    public const ACTION = 4;

    private readonly LinkCommitter $committer;

    public function __construct(private readonly ActionServices $services)
    {
        $this->committer = new LinkCommitter($services);
    }

    public function committer(): LinkCommitter
    {
        return $this->committer;
    }

    /**
     * 衛星の対象区域（区域 ID の昇順）。通信確立できない衛星（禁止効果）は空。
     *
     * @return list<int>
     */
    public function targetCells(int $playerId, Satellite $satellite, StepMode $mode): array
    {
        if (!$satellite->isActive() || $satellite->cell === null || $satellite->direction === null) {
            return [];
        }
        if ($this->services->hooks->isForbidden(ForbiddenAction::Link, $satellite)) {
            return [];
        }
        $geometry = $this->services->layout->geometry();
        $cells = [$satellite->cell => true];
        if ($mode === StepMode::Bonus) {
            $here = $geometry->cellFromId($satellite->cell);
            foreach (Adjacency::forwardBackward($geometry, $here->x, $here->y, $satellite->direction) as $cell) {
                $cells[$geometry->cellId($cell->x, $cell->y)] = true;
            }
        }
        foreach ($this->services->hooks->extraLinkTargets($playerId, $satellite, $mode) as $cellId) {
            $cells[$cellId] = true;
        }
        $ids = array_keys($cells);
        sort($ids);
        return $ids;
    }

    /**
     * 通信確立コスト。$special は特殊通信確立（特殊コスト 5 を、下限 0 の後に足す）。
     */
    public function cost(int $playerId, Satellite $satellite, int $cell, bool $special = false): LinkCost
    {
        $type = $this->services->layout->areaType($cell);
        $reef = $this->hasAdjacentOwnReef($playerId, $cell, $type) ? 1 : 0;
        $baseArea = $type->areaCost();
        $afterReef = max(0, $baseArea - $reef);

        $shares = [];
        if (!$this->services->hooks->pioneerWaived($playerId, $satellite)) {
            foreach ($this->pioneerRecipients($playerId, $cell) as $to) {
                $shares[] = ['to' => $to, 'amount' => LinkCost::PIONEER_UNIT];
            }
        }

        $reduction = max(0, $this->services->hooks->linkCostReduction($playerId, $satellite, $cell));
        $area = max(0, $afterReef - $reduction);
        $remaining = max(0, $reduction - $afterReef);
        foreach ($shares as $i => $share) {
            $cut = min($remaining, $share['amount']);
            $shares[$i]['amount'] = $share['amount'] - $cut;
            $remaining -= $cut;
        }
        $shares = array_values(array_filter($shares, static fn(array $s): bool => $s['amount'] > 0));

        return new LinkCost($baseArea, $reef, $reduction, $area, $shares, $special ? LinkCost::SPECIAL_COST : 0);
    }

    /**
     * 先駆コストの受け取り人: その区域に自分以外のキューブがあるプレイヤーと、他プレイヤーの発射拠点の持ち主（001 U-10）。席順。
     *
     * @return list<int>
     */
    public function pioneerRecipients(int $playerId, int $cell): array
    {
        $ids = [];
        foreach ($this->services->links->inCell($cell) as $link) {
            if ($link->playerId !== $playerId) {
                $ids[] = $link->playerId;
            }
        }
        foreach ($this->services->players->padCells() as $owner => $padCell) {
            if ($padCell === $cell && $owner !== $playerId) {
                $ids[] = $owner;
            }
        }
        return $this->services->seatOrder($ids);
    }

    /**
     * 通信確立できる区域の候補（区域 ID の昇順）。払えない区域も含み、isSelectable() で区別する。
     *
     * @return list<LinkCandidate>
     */
    public function candidates(int $playerId, StepMode $mode): array
    {
        if ($this->remainingCubes($playerId) <= 0) {
            return [];
        }
        $ownPad = $this->services->players->padCell($playerId);
        $own = $this->services->links->cellsFor($playerId);
        $byCell = [];
        foreach ($this->services->satellites->forPlayer($playerId) as $sat) {
            foreach ($this->targetCells($playerId, $sat, $mode) as $cell) {
                if ($cell === $ownPad || in_array($cell, $own, true)) {
                    continue;
                }
                $cost = $this->cost($playerId, $sat, $cell);
                $byCell[$cell][] = new LinkSatelliteOption(
                    $sat,
                    $cost,
                    $this->services->selling->assess($playerId, $cost->total()),
                );
            }
        }
        ksort($byCell);
        $result = [];
        foreach ($byCell as $cell => $options) {
            $result[] = new LinkCandidate($cell, $options);
        }
        return $result;
    }

    /** 残りのキューブ（16 − 通信確立済み）。発射拠点は数えない */
    public function remainingCubes(int $playerId): int
    {
        return LinkRepository::CUBES_PER_PLAYER - $this->services->links->countFor($playerId);
    }

    public function participants(StepMode $mode): array
    {
        return $mode === StepMode::Main ? $this->services->players->playerIds() : $this->services->bonusHolders(self::ACTION);
    }

    public function canAct(int $playerId, StepMode $mode): bool
    {
        foreach ($this->candidates($playerId, $mode) as $candidate) {
            if ($candidate->isSelectable()) {
                return true;
            }
        }
        return false;
    }

    public function validate(int $playerId, StepMode $mode, array $data): void
    {
        $op = $data['op'] ?? null;
        if ($op === 'pass') {
            return;
        }
        if ($op !== 'link') {
            throw new RuleViolation('Select an area to link, or pass');
        }
        $option = $this->optionFor($playerId, $mode, $data);
        $this->services->selling->assertPayable($playerId, $option->cost->total(), $this->sellList($data));
    }

    /** zombie: [Pass]（キューブを放棄。コストを払わない。008 §3-4） */
    public function defaultChoice(int $playerId, StepMode $mode): array
    {
        return ['op' => 'pass'];
    }

    public function resolve(StepMode $mode, array $choices): StepResult
    {
        // 全員の依頼を処理前の盤面・所持金で作ってから、まとめてコミットする
        $requests = [];
        $outcomes = [];
        foreach ($this->services->seatOrder(array_keys($choices)) as $playerId) {
            $data = $choices[$playerId];
            if (($data['op'] ?? 'pass') !== 'link') {
                $outcomes[$playerId] = new StepOutcome($playerId, 'pass');
                continue;
            }
            try {
                $option = $this->optionFor($playerId, $mode, $data);
            } catch (RuleViolation) {
                $outcomes[$playerId] = new StepOutcome($playerId, 'pass', true);
                continue;
            }
            $requests[] = new LinkRequest(
                $playerId,
                $option->satellite->id,
                (int) $data['cell'],
                false,
                $option->cost,
                $this->sellList($data),
            );
        }

        $commit = $this->committer->commit($requests);
        foreach ($commit->failed as $request) {
            $outcomes[$request->playerId] = new StepOutcome($request->playerId, 'pass', true);
        }
        foreach ($commit->entries as $entry) {
            $outcomes[$entry->request->playerId] = new StepOutcome($entry->request->playerId, 'link', false, [
                'cell' => $entry->request->cell,
                'sat' => $entry->request->satelliteId,
                'slot' => $entry->link->slot,
                'level' => $entry->link->level,
                'cost' => $entry->payment->cost,
                'sold' => $entry->payment->soldCards,
                'areaCost' => $entry->request->cost->areaCost,
                'pioneer' => $entry->request->cost->pioneer,
                'bonus' => $entry->bonus,
            ]);
        }
        $ordered = [];
        foreach ($this->services->seatOrder(array_keys($outcomes)) as $playerId) {
            $ordered[] = $outcomes[$playerId];
        }
        return new StepResult($ordered, ['commit' => $commit]);
    }

    /**
     * @param array<mixed> $data
     * @throws RuleViolation
     */
    private function optionFor(int $playerId, StepMode $mode, array $data): LinkSatelliteOption
    {
        $cell = $data['cell'] ?? null;
        $satId = $data['sat'] ?? null;
        if (!is_int($cell) || !is_int($satId)) {
            throw new RuleViolation('Select an area and a satellite');
        }
        if ($this->remainingCubes($playerId) <= 0) {
            throw new RuleViolation('You have no cubes left');
        }
        foreach ($this->candidates($playerId, $mode) as $candidate) {
            if ($candidate->cell !== $cell) {
                continue;
            }
            foreach ($candidate->options as $option) {
                if ($option->satellite->id === $satId) {
                    return $option;
                }
            }
        }
        throw new RuleViolation('This satellite cannot link that area');
    }

    private function hasAdjacentOwnReef(int $playerId, int $cell, AreaType $type): bool
    {
        if (!$type->isLand()) {
            return false;
        }
        $geometry = $this->services->layout->geometry();
        $here = $geometry->cellFromId($cell);
        $own = $this->services->links->cellsFor($playerId);
        foreach (Adjacency::neighbors($geometry, $here->x, $here->y) as $near) {
            $id = $geometry->cellId($near->x, $near->y);
            if (in_array($id, $own, true) && $this->services->layout->areaType($id) === AreaType::REEF) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<mixed> $data
     * @return list<int>
     */
    private function sellList(array $data): array
    {
        $sell = $data['sell'] ?? [];
        if (!is_array($sell)) {
            throw new RuleViolation('Invalid sale');
        }
        return array_values(array_map('intval', $sell));
    }
}
