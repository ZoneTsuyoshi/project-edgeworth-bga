<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Stats;

/**
 * 統計の読み書き口（仕様書 004 §6.1）。BGA の tableStats / playerStats を包み、テストでは配列の実装に差し替える。
 */
interface StatStore
{
    public function tableInit(string $name, int|float|bool $value): void;

    public function tableSet(string $name, int|float|bool $value): void;

    public function tableInc(string $name, int $delta): void;

    /** 全プレイヤーの統計を初期化する */
    public function playerInit(string $name, int|float|bool $value): void;

    public function playerSet(string $name, int $playerId, int|float|bool $value): void;

    public function playerInc(string $name, int $playerId, int $delta): void;
}
