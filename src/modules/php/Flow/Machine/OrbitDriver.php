<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

use Bga\Games\projectedgeworth\Effects\Runtime\ActionEvents;
use Bga\Games\projectedgeworth\Flow\NoOrbitModifiers;
use Bga\Games\projectedgeworth\Flow\OrbitModifiers;
use Bga\Games\projectedgeworth\Flow\OrbitPhase;
use Bga\Games\projectedgeworth\Flow\OrbitSatellite;
use Bga\Games\projectedgeworth\Flow\StateId;
use Bga\Games\projectedgeworth\Scoring\ScoringBoard;

/**
 * 100 OrbitDriver: 循環フェイズ（001 §9、009 §3.8）。
 * ステップ 0: 窓 O0。1: 移動 → 窓 O1。2: 燃料消費。3: ニアミス判定。4: 着陸 / 着水判定 → 窓 O4。5: 着陸猶予を終わらせる。
 * O2・O3 は修正効果のみなので窓にしない（009 §3.8）。
 */
final class OrbitDriver
{
    public function __construct(
        private readonly FlowContext $ctx,
        private readonly OrbitModifiers $mods = new NoOrbitModifiers(),
    ) {
    }

    public function run(): DriverResult
    {
        $ops = ['win:O0', 'move', 'win:O1', 'fuel', 'near', 'landing', 'win:O4', 'finish', 'next'];
        return (new StepProgram($this->ctx, StateId::ORBIT_DRIVER))->run($ops, function (string $op, NoticeList $notes): ?string {
            $orbit = $this->orbit();
            $services = $this->ctx->services;
            switch ($op) {
                case 'move':
                    $before = $this->bySatId($orbit->activeSatellites());
                    $after = $orbit->stepMove($services->layout->geometry(), $this->mods);
                    $moves = [];
                    foreach ($after as $sat) {
                        $moves[] = [
                            'sat_id' => $sat->satId,
                            'from_cell' => $before[$sat->satId]->cell,
                            'cell' => $sat->cell,
                            'dir' => $sat->dir->value,
                            'reflected' => $sat->dir !== $before[$sat->satId]->dir,
                        ];
                    }
                    if ($moves !== []) {
                        $notes->add($this->ctx->notices->satellitesMoved($moves));
                    }
                    $this->ctx->record(...ActionEvents::entered($services->layout, $before, $after));
                    break;
                case 'fuel':
                    $before = $this->bySatId($orbit->activeSatellites());
                    $notes->addAll($this->fuelNotices($before, $orbit->stepFuel($this->mods), 'orbit'));
                    break;
                case 'near':
                    $before = $this->bySatId($orbit->activeSatellites());
                    $ids = $orbit->stepNearMiss($this->mods);
                    $byCell = [];
                    foreach ($ids as $satId) {
                        $byCell[$before[$satId]->cell][] = $satId;
                    }
                    ksort($byCell);
                    foreach ($byCell as $cell => $satIds) {
                        $notes->add($this->ctx->notices->nearMiss($cell, $satIds));
                    }
                    $notes->addAll($this->fuelNotices($before, $orbit->activeSatellites(), 'nearMiss'));
                    break;
                case 'landing':
                    $landings = $orbit->stepLanding(ScoringBoard::fromLayout($services->layout));
                    $this->ctx->record(...ActionEvents::landed($landings));
                    foreach ($landings as $landing) {
                        $sat = $services->satellites->get($landing->satId);
                        $notes->add($this->ctx->notices->satelliteLanded($sat, $landing->cell, $landing->sea, $landing->maya));
                        $notes->add($this->ctx->notices->mayaChanged(
                            $landing->playerId,
                            $landing->maya,
                            $services->players->maya($landing->playerId),
                            'landing',
                        ));
                    }
                    $notes->add($this->ctx->counters());
                    break;
                case 'finish':
                    $orbit->finish();
                    break;
                default:
                    throw new \LogicException("unknown op: $op");
            }
            return null;
        });
    }

    private function orbit(): OrbitPhase
    {
        $services = $this->ctx->services;
        return new OrbitPhase($services->satellites, $services->cards, $services->players);
    }

    /**
     * @param list<OrbitSatellite> $sats
     * @return array<int, OrbitSatellite>
     */
    private function bySatId(array $sats): array
    {
        $map = [];
        foreach ($sats as $sat) {
            $map[$sat->satId] = $sat;
        }
        return $map;
    }

    /**
     * 燃料の増減（N19）。
     *
     * @param array<int, OrbitSatellite> $before
     * @param list<OrbitSatellite> $after
     * @return list<Notice>
     */
    private function fuelNotices(array $before, array $after, string $reason): array
    {
        $notices = [];
        foreach ($after as $sat) {
            $delta = $sat->fuelUsed - $before[$sat->satId]->fuelUsed;
            $notice = $this->ctx->notices->fuelChanged(
                $this->ctx->services->satellites->get($sat->satId),
                $delta,
                $sat->fuelUsed,
                $sat->capacity,
                $reason,
            );
            if ($notice !== null) {
                $notices[] = $notice;
            }
        }
        return $notices;
    }
}
