<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Flow;

use Bga\Games\ProjectEdgeworth\Board\BoardGeometry;
use Bga\Games\ProjectEdgeworth\Data\PlayerValues;
use Bga\Games\ProjectEdgeworth\Data\ProjectCardRepository;
use Bga\Games\ProjectEdgeworth\Data\SatelliteRepository;
use Bga\Games\ProjectEdgeworth\Scoring\ScoringBoard;

/**
 * 循環フェイズの DB への反映（001 §9、009 の 100 OrbitDriver）。計算は OrbitResolver、ここは読み書きだけ。
 * 状態機械は窓（O0・O1・O4）の合間に step* を 1 つずつ呼ぶ。窓を挟まないテスト・簡易実行には run() を使う。
 * 呼び出し順: stepMove → (O1 の窓) → stepFuel → stepNearMiss → stepLanding → (O4 の窓) → finish。
 */
final class OrbitPhase
{
    public function __construct(
        private readonly SatelliteRepository $satellites,
        private readonly ProjectCardRepository $projectCards,
        private readonly PlayerValues $players,
    ) {
    }

    /**
     * 稼働中の全衛星（打ち上げたラウンドの衛星も含む。FAQ 行 38）。sat_id の昇順。
     *
     * @return list<OrbitSatellite>
     */
    public function activeSatellites(): array
    {
        $result = [];
        foreach ($this->satellites->all() as $sat) {
            if ($sat->cell === null || $sat->direction === null) {
                continue;
            }
            $deployed = array_map(
                static fn(int $id): string => sprintf('P%03d', $id),
                $this->projectCards->deployedIds($sat->id),
            );
            $result[] = new OrbitSatellite(
                $sat->id,
                $sat->playerId,
                $sat->cell,
                $sat->direction,
                $sat->fuelUsed,
                FuelMath::capacity($sat->cardId, $deployed),
            );
        }
        usort($result, static fn(OrbitSatellite $a, OrbitSatellite $b): int => $a->satId <=> $b->satId);
        return $result;
    }

    /**
     * 衛星コマの移動（追加移動を含む）。
     *
     * @return list<OrbitSatellite> 移動後
     */
    public function stepMove(BoardGeometry $geometry, OrbitModifiers $mods): array
    {
        $moved = OrbitResolver::move($geometry, $this->activeSatellites(), $mods);
        foreach ($moved as $sat) {
            $this->satellites->move($sat->satId, $sat->cell, $sat->dir);
        }
        return $moved;
    }

    /**
     * 燃料消費。
     *
     * @return list<OrbitSatellite> 消費後
     */
    public function stepFuel(OrbitModifiers $mods): array
    {
        $after = OrbitResolver::consumeFuel($this->activeSatellites(), $mods);
        $this->writeFuel($after);
        return $after;
    }

    /**
     * ニアミス判定と燃料消費。
     *
     * @return list<int> ニアミスした衛星の ID
     */
    public function stepNearMiss(OrbitModifiers $mods): array
    {
        $sats = $this->activeSatellites();
        $ids = OrbitResolver::nearMissIds($sats, $mods);
        $this->writeFuel(OrbitResolver::applyNearMiss($sats, $ids, $mods));
        return $ids;
    }

    /**
     * 着陸 / 着水判定。着陸した衛星を非稼働にし（燃料は 0 に戻る）、着陸猶予を付ける（O4 の窓が閉じるまで
     * 「稼働中のみ」の効果が有効。FAQ 行 32）。海区域なら持ち主に 10 マヤ。
     *
     * @return list<Landing>
     */
    public function stepLanding(ScoringBoard $board): array
    {
        $landings = OrbitResolver::landings($this->activeSatellites(), $board);
        foreach ($landings as $landing) {
            $this->satellites->setGrace($landing->satId, true);
            $this->satellites->deactivate($landing->satId);
            if ($landing->maya > 0) {
                $this->players->addMaya($landing->playerId, $landing->maya);
            }
        }
        return $landings;
    }

    /** 循環フェイズの終わり: 着陸猶予を外す（名声 VI が着陸した衛星を稼働中と数えないため。009 §7） */
    public function finish(): void
    {
        $this->satellites->clearAllGrace();
    }

    /**
     * 窓を挟まずに全ステップを実行する。
     */
    public function run(BoardGeometry $geometry, ScoringBoard $board, OrbitModifiers $mods = new NoOrbitModifiers()): OrbitReport
    {
        $this->stepMove($geometry, $mods);
        $this->stepFuel($mods);
        $nearMiss = $this->stepNearMiss($mods);
        $landings = $this->stepLanding($board);
        $this->finish();
        return new OrbitReport($nearMiss, $landings);
    }

    /**
     * @param list<OrbitSatellite> $after
     */
    private function writeFuel(array $after): void
    {
        foreach ($after as $sat) {
            $before = $this->satellites->get($sat->satId)->fuelUsed;
            if ($sat->fuelUsed !== $before) {
                $this->satellites->addFuelUsed($sat->satId, $sat->fuelUsed - $before, $sat->capacity);
            }
        }
    }
}
