<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Data\CardLocation;
use Bga\Games\projectedgeworth\Data\FlowPhase;
use Bga\Games\projectedgeworth\Data\LinkRepository;
use Bga\Games\projectedgeworth\Data\MissionRepository;
use Bga\Games\projectedgeworth\Data\RegionRepository;
use Bga\Games\projectedgeworth\Data\Satellite;

/**
 * getAllDatas の中身（010 §8.2）。閲覧者に見せてよい情報だけを返す専用の読み出しで、project_card や player を丸ごと整形しない
 * （010 §8.2 末尾、§9.1）。非公開情報は本人の手札・引いた直後のカード・ミッションだけ。山札は枚数のみ、他人の手札は枚数のみ。
 * 一時的な情報（同時選択の確定内容、窓の問い）は含めず、現在の状態の getArgs の `_private` が復元する。
 *
 * 窓の最中は、他プレイヤーの分を window_snapshot（010 §9.4）から返す必要がある。窓の状態（30〜32）は今回は素通りで
 * 何も書かないので未実装（効果エンジンの結線、申し送り N-4 で追加する）。
 */
final class PublicView
{
    public function __construct(private readonly FlowContext $ctx)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $viewerId): array
    {
        $services = $this->ctx->services;
        $globals = $services->globals;
        $order = $services->players->playerIds();
        $isPlayer = in_array($viewerId, $order, true);
        $missions = new MissionRepository($this->ctx->db);

        $finalScores = null;
        if ($globals->flowPhase() === FlowPhase::End && $this->scored($order)) {
            $finalScores = [];
            foreach ($missions->allDealt() as $playerId => $nos) {
                $finalScores[$playerId] = ['fame' => $services->players->fameDetail($playerId), 'missions' => $nos];
            }
        }

        return [
            'round' => $globals->roundNo(),
            'phase' => $globals->flowPhase()->value,
            'basePlayer' => $globals->basePlayer(),
            'board' => $this->board(),
            'rewards' => $globals->rewardCards(),
            'deckCount' => $this->ctx->deckCount(),
            'discard' => $services->cards->idsIn(CardLocation::Discard),
            'playerOrder' => $order,
            'myHand' => $isPlayer ? $services->cards->idsIn(CardLocation::Hand, $viewerId) : [],
            'myDrawn' => $isPlayer ? $services->cards->idsIn(CardLocation::Drawn, $viewerId) : [],
            'myMissions' => $isPlayer ? $missions->forPlayer($viewerId) : [],
            'playerData' => $this->playerData($order),
            'bonusHolders' => $this->bonusHolders(),
            'window' => $globals->windowId() === null ? null : ['id' => $globals->windowId()],
            'finalScores' => $finalScores,
        ];
    }

    /**
     * @param list<int> $order
     */
    private function scored(array $order): bool
    {
        foreach ($order as $playerId) {
            if ($this->ctx->services->players->fameDetail($playerId) === null) {
                return false;
            }
        }
        return $order !== [];
    }

    /**
     * @return array<string, mixed>
     */
    private function board(): array
    {
        $services = $this->ctx->services;
        $layout = $services->layout;
        $geometry = $layout->geometry();
        $regions = [];
        foreach ((new RegionRepository($this->ctx->db))->all() as $r) {
            $regions[] = ['br' => $r['row'], 'bc' => $r['col'], 'board' => $r['board'], 'flip' => $r['flipped']];
        }
        $cells = [];
        foreach ($layout->cellIds() as $cellId) {
            $cell = $geometry->cellFromId($cellId);
            $cells[] = [
                'cell' => $cellId,
                'x' => $cell->x,
                'y' => $cell->y,
                'type' => strtoupper($layout->areaType($cellId)->value),
                'region' => $layout->region($cellId)->value,
            ];
        }
        return [
            'cols' => $geometry->columns(),
            'rows' => $geometry->rows(),
            'rowShift' => $geometry->parity(),
            'face' => $layout->face()->name,
            'regions' => $regions,
            'cells' => $cells,
        ];
    }

    /**
     * @param list<int> $order
     * @return array<int, array<string, mixed>>
     */
    private function playerData(array $order): array
    {
        $services = $this->ctx->services;
        $public = $services->players->publicValues();
        $actionCards = $services->actionCards->all();
        $data = [];
        foreach ($order as $playerId) {
            $links = [];
            foreach ($services->links->forPlayer($playerId) as $link) {
                $links[] = ['cell' => $link->cell, 'slot' => $link->slot, 'level' => $link->level];
            }
            $cards = [];
            foreach ($actionCards[$playerId] ?? [] as $actionNo => $state) {
                $cards[$actionNo] = $state->value;
            }
            $data[$playerId] = [
                'maya' => $public[$playerId]['maya'],
                'budget' => $public[$playerId]['budget'],
                'padCell' => $public[$playerId]['padCell'],
                'handCount' => $services->cards->countIn(CardLocation::Hand, $playerId),
                'drawnCount' => $services->cards->countIn(CardLocation::Drawn, $playerId),
                'missionCount' => count((new MissionRepository($this->ctx->db))->forPlayer($playerId)),
                'cubesLeft' => LinkRepository::CUBES_PER_PLAYER - count($links),
                'actionCards' => $cards,
                'satellites' => array_map(fn(Satellite $sat): array => $this->satellite($sat), $services->satellites->forPlayer($playerId)),
                'links' => $links,
            ];
        }
        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function satellite(Satellite $sat): array
    {
        $services = $this->ctx->services;
        $cards = [];
        foreach ($services->cards->deployedIds($sat->id) as $cardId) {
            $cards[] = ['id' => $cardId, 'activation' => $services->cards->activation($cardId)];
        }
        return [
            'satId' => $sat->id,
            'shape' => $sat->shape->value,
            'plan' => $sat->plan->value,
            'card' => $sat->cardId,
            'cell' => $sat->cell,
            'dir' => $sat->direction?->value,
            'fuelUsed' => $sat->fuelUsed,
            'fuelCapacity' => $services->fuelCapacity($sat->id),
            'grace' => $sat->grace,
            'activation' => $sat->activation,
            'cards' => $cards,
        ];
    }

    /**
     * @return array<int, list<int>>
     */
    private function bonusHolders(): array
    {
        return $this->ctx->services->globals->bonusHolders();
    }
}
