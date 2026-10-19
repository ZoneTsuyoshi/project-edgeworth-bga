<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Data;

/**
 * region テーブル（地域ボードの配置。010 §5.5）。setupNewGame で 1 回だけ書き、ゲーム中は変えない。
 * 面（表 / 裏）と行のずれ P は全ボード共通なのでグローバル値に持つ。
 */
final class RegionRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * ボードを 1 枚置く。同じボードを 2 回、同じ位置に 2 枚は置けない（DB の一意制約）。
     *
     * @param int $boardRow 段（0 = 上段、1 = 下段）
     * @param int $boardCol 段内の位置（0〜B−1）
     * @param string $board 地域ボード（A〜H）
     */
    public function place(int $boardRow, int $boardCol, string $board, bool $flipped): void
    {
        if (preg_match('/^[A-H]$/', $board) !== 1) {
            throw new \InvalidArgumentException("invalid region board: $board");
        }
        $this->db->execute(
            'INSERT INTO `region` (`region_br`, `region_bc`, `region_board`, `region_flip`) VALUES ('
            . "$boardRow, $boardCol, '$board', " . ($flipped ? 1 : 0) . ')',
        );
    }

    /**
     * @return list<array{row: int, col: int, board: string, flipped: bool}> 段・位置の順
     */
    public function all(): array
    {
        $rows = $this->db->rows(
            'SELECT `region_br`, `region_bc`, `region_board`, `region_flip` FROM `region` ORDER BY `region_br`, `region_bc`',
        );
        return array_map(static fn(array $r): array => [
            'row' => (int) $r['region_br'],
            'col' => (int) $r['region_bc'],
            'board' => (string) $r['region_board'],
            'flipped' => (int) $r['region_flip'] === 1,
        ], $rows);
    }
}
