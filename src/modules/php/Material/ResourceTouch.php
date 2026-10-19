<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * 効果が触れる資源 1 件（記号と範囲。011 §6-4）。`EffectSpec` では範囲が `HostSatellite` / `EventSatellite` のままで、
 * プールに積むときに `bind()` で具体的な衛星 ID に結ぶ。
 */
final readonly class ResourceTouch
{
    public function __construct(
        public ResourceSymbol $symbol,
        public ResourceScope $scope = ResourceScope::Player,
        public ?int $satelliteId = null,
    ) {
    }

    /** 衛星 ID に結ぶ。範囲は `Player` / `AllSatellites` のときは変えない。結べない（衛星なし）ときは `AllSatellites` に広げる */
    public function bind(?int $hostSatelliteId, ?int $eventSatelliteId): self
    {
        $id = match ($this->scope) {
            ResourceScope::HostSatellite => $hostSatelliteId,
            ResourceScope::EventSatellite => $eventSatelliteId,
            default => null,
        };
        if ($this->scope === ResourceScope::HostSatellite || $this->scope === ResourceScope::EventSatellite) {
            return $id === null
                ? new self($this->symbol, ResourceScope::AllSatellites)
                : new self($this->symbol, ResourceScope::Satellite, $id);
        }

        return $this;
    }

    /** 範囲が重なるか。`Player` は衛星の範囲とは重ならない。`AllSatellites` は任意の衛星の範囲と重なる */
    public function overlaps(self $other): bool
    {
        $mine = $this->scope === ResourceScope::Player;
        $theirs = $other->scope === ResourceScope::Player;
        if ($mine || $theirs) {
            return $mine && $theirs;
        }
        if ($this->scope !== ResourceScope::Satellite || $other->scope !== ResourceScope::Satellite) {
            return true; // AllSatellites、および結んでいない範囲は安全側（全衛星）として扱う
        }

        return $this->satelliteId === $other->satelliteId;
    }

    /**
     * 007 §6-2 の規則 1〜3: 同じ資源で範囲が重なり、(1) どちらかが読み書き、または (2) 異種の加算（`F+` と `F-`）。
     * 同種の加算同士（`M+` と `M+` など）と、読み同士（`Cr` と `Cr`）は衝突しない。規則 3（`Cr` と `C`）は規則 1 に含まれる。
     */
    public function conflictsWith(self $other): bool
    {
        if ($this->symbol->group() !== $other->symbol->group() || !$this->overlaps($other)) {
            return false;
        }
        if ($this->symbol->isReadWrite() || $other->symbol->isReadWrite()) {
            return true;
        }

        return $this->symbol !== $other->symbol && $this->symbol->group() === 'F';
    }
}
