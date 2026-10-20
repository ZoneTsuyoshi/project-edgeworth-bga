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
 * 窓の最中は、他プレイヤーの分と山の枚数を window_snapshot（010 §9.4）から返す。
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

        $deckCount = $this->ctx->deckCount();
        $discard = $services->cards->idsIn(CardLocation::Discard);
        $playerData = $this->playerData($order);
        $snapshot = $globals->windowSnapshot();
        $publicFame = null;
        if ($snapshot !== null) {
            // 窓の最中は、他人の変化を窓を開いた時点の値で返す（010 §9.4）。本人の分は DB の現在値
            $deckCount = (int) ($snapshot['deckCount'] ?? $deckCount);
            $discard = array_values(array_map('intval', (array) ($snapshot['discard'] ?? $discard)));
            $publicFame = $snapshot['publicFame'] ?? null;
            foreach ((array) ($snapshot['playerData'] ?? []) as $playerId => $data) {
                if ((int) $playerId !== $viewerId) {
                    $playerData[(int) $playerId] = $data;
                }
            }
        }

        $view = [
            'round' => $globals->roundNo(),
            'phase' => $globals->flowPhase()->value,
            'basePlayer' => $globals->basePlayer(),
            'board' => $this->board(),
            'rewards' => $globals->rewardCards(),
            'deckCount' => $deckCount,
            'discard' => $discard,
            'playerOrder' => $order,
            'myHand' => $isPlayer ? $services->cards->idsIn(CardLocation::Hand, $viewerId) : [],
            'myDrawn' => $isPlayer ? $services->cards->idsIn(CardLocation::Drawn, $viewerId) : [],
            'myMissions' => $isPlayer ? $missions->forPlayer($viewerId) : [],
            'playerData' => $playerData,
            'bonusHolders' => $this->bonusHolders(),
            'window' => $globals->windowId() === null ? null : ['id' => $globals->windowId()],
            'finalScores' => $finalScores,
        ];
        if ($this->ctx->publicFame) {
            // ゲームオプション 105 が Visible のときだけ（ミッション V を除く途中の名声。010 §8.2）
            // 窓の最中は全員に窓開始時の値（snapshot）を返す
            $view['publicFame'] = $publicFame ?? $this->ctx->finalScoring()->publicFame();
        }
        return $view;
    }

    /**
     * 窓を開いた時点の公開情報（`window_snapshot`。010 §9.4）。山札の枚数・捨て札・全員の公開データ。
     *
     * @return array<string, mixed>
     */
    public function snapshotData(): array
    {
        $services = $this->ctx->services;

        $data = [
            'deckCount' => $this->ctx->deckCount(),
            'discard' => $services->cards->idsIn(CardLocation::Discard),
            'playerData' => $this->playerData($services->players->playerIds()),
        ];
        if ($this->ctx->publicFame) {
            $data['publicFame'] = $this->ctx->finalScoring()->publicFame();
        }
        return $data;
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
