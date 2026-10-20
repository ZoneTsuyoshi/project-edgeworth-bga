<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Runtime;

use Bga\Games\projectedgeworth\Board\BoardLayout;
use Bga\Games\projectedgeworth\Effects\Context\GameEvent;
use Bga\Games\projectedgeworth\Flow\Action\StagedLinkConverter;
use Bga\Games\projectedgeworth\Flow\Action\StepResult;
use Bga\Games\projectedgeworth\Flow\Landing;
use Bga\Games\projectedgeworth\Flow\OrbitSatellite;
use Bga\Games\projectedgeworth\Material\ActionKind;
use Bga\Games\projectedgeworth\Material\Hook;
use Bga\Games\projectedgeworth\Material\ProjectCards;

/**
 * ドライバが起こした出来事（コミットの結果、公開、移動、着陸）から、窓フックのイベントを作る（011 §3-1）。
 */
final class ActionEvents
{
    private function __construct()
    {
    }

    /**
     * II・III・IV のメイン / ボーナスのコミットの結果から。`DEPLOYED`・`LAUNCHED`・`TURNED`・`LINKED`（世代 0）。
     *
     * @return list<GameEvent>
     */
    public static function fromCommit(int $actionNo, StepResult $result): array
    {
        $events = [];
        foreach ($result->outcomes as $outcome) {
            $d = $outcome->details;
            if ($actionNo === 2 && $outcome->op === 'deploy') {
                $events[] = new GameEvent(
                    Hook::Deployed,
                    $outcome->playerId,
                    (int) $d['sat'],
                    (int) $d['card'],
                    ProjectCards::get(sprintf('P%03d', (int) $d['card']))->type,
                );
            } elseif ($actionNo === 3 && $outcome->op === 'launch') {
                $events[] = new GameEvent(Hook::Launched, $outcome->playerId, (int) $d['sat']);
            } elseif ($actionNo === 3 && $outcome->op === 'turn') {
                $events[] = new GameEvent(Hook::Turned, $outcome->playerId, (int) $d['sat']);
            }
        }
        if ($actionNo === 4 && isset($result->extra['commit'])) {
            array_push($events, ...StagedLinkConverter::events($result->extra['commit'], 0));
        }

        return $events;
    }

    /**
     * II・III のボーナスの保持者に、`ACTION_BONUS_STEP`（R012・R013）。
     *
     * @param list<int> $holders
     * @return list<GameEvent>
     */
    public static function bonusStep(int $actionNo, array $holders): array
    {
        $action = match ($actionNo) {
            2 => ActionKind::Deploy,
            3 => ActionKind::LaunchTurn,
            default => null,
        };
        if ($action === null) {
            return [];
        }

        return array_map(
            static fn(int $p): GameEvent => new GameEvent(Hook::ActionBonusStep, $p, data: ['action' => $action->value]),
            $holders,
        );
    }

    /**
     * 回収（21）の前に縦向きだったアクションカード（`PLANNING_RETRIEVE`。P001・P002）。
     *
     * @param array<int, list<int>> $uprightByPlayer プレイヤー ID => 縦向きのアクション番号
     * @return list<GameEvent>
     */
    public static function retrieve(array $uprightByPlayer): array
    {
        $events = [];
        foreach ($uprightByPlayer as $playerId => $upright) {
            if ($upright !== []) {
                $events[] = new GameEvent(Hook::PlanningRetrieve, $playerId, data: ['upright' => $upright]);
            }
        }

        return $events;
    }

    /**
     * 公開（23）。全員の選択（`PLANNING_REVEAL`。プレイヤーなし = 全員のプールへ）。
     *
     * @param array<int, int> $choices プレイヤー ID => アクション番号
     */
    public static function reveal(array $choices): GameEvent
    {
        return new GameEvent(Hook::PlanningReveal, null, data: ['choices' => $choices]);
    }

    /**
     * 企画で引いた枚数（`PROPOSAL_DRAWN`。P075）。
     *
     * @param array<int, int> $counts プレイヤー ID => 引いた枚数
     * @return list<GameEvent>
     */
    public static function proposalDrawn(array $counts): array
    {
        $events = [];
        foreach ($counts as $playerId => $count) {
            if ($count > 0) {
                $events[] = new GameEvent(Hook::ProposalDrawn, $playerId, data: ['count' => $count]);
            }
        }

        return $events;
    }

    /**
     * 移動した衛星（`ORBIT_ENTERED`。移動前後の地域）。
     *
     * @param array<int, OrbitSatellite> $before 衛星 ID => 移動前
     * @param list<OrbitSatellite> $after
     * @return list<GameEvent>
     */
    public static function entered(BoardLayout $layout, array $before, array $after): array
    {
        $events = [];
        foreach ($after as $sat) {
            $events[] = new GameEvent(Hook::OrbitEntered, $sat->playerId, $sat->satId, cell: $sat->cell, data: [
                'fromRegion' => $layout->region($before[$sat->satId]->cell)->value,
                'toRegion' => $layout->region($sat->cell)->value,
            ]);
        }

        return $events;
    }

    /**
     * 着陸 / 着水した衛星（`LANDED`）。
     *
     * @param list<Landing> $landings
     * @return list<GameEvent>
     */
    public static function landed(array $landings): array
    {
        return array_map(
            static fn(Landing $l): GameEvent => new GameEvent(
                Hook::Landed,
                $l->playerId,
                $l->satId,
                cell: $l->cell,
                data: ['areaKind' => $l->sea ? 'SEA' : 'LAND'],
            ),
            $landings,
        );
    }
}
