<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

/**
 * 1 回のコミットで起きるマヤの増減を、処理前の所持金から順に積み上げ、N12 の maya（変更後）を作る。
 * 一斉処理の途中経過は誰にも見えないので、通知の順に積み上げた値でよい（最後は DB の値と一致する）。
 */
final class MayaTracker
{
    /** @var array<int, int> */
    private array $running;

    /**
     * @param array<int, int> $before プレイヤー ID => 処理前の所持マヤ
     */
    public function __construct(private readonly Notices $notices, array $before)
    {
        $this->running = $before;
    }

    public function change(int $playerId, int $delta, string $reason): ?Notice
    {
        $this->running[$playerId] = ($this->running[$playerId] ?? 0) + $delta;
        return $this->notices->mayaChanged($playerId, $delta, $this->running[$playerId], $reason);
    }

    public function current(int $playerId): int
    {
        return $this->running[$playerId] ?? 0;
    }
}
