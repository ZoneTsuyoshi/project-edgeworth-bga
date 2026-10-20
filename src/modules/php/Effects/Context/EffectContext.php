<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Context;

/**
 * 部品に渡すゲーム状態への接点。
 */
final readonly class EffectContext
{
    /**
     * @param (\Closure(): list<StagedLinkRequest>)|null $stagedSource 窓の中でステージされた（まだコミットされていない）通信確立を返す
     *                                                                   （エンジンが渡す。R11 が同じ窓の予約を数えるため。007 §7-3）
     */
    public function __construct(
        public GameView $view,
        public GameMutator $mutator,
        private ?\Closure $stagedSource = null,
    ) {
    }

    /**
     * ステージされた通信確立（コミット前の予約）。キューブと所持金の予約、同じ区域の重複を避けるために数える。
     *
     * @return list<StagedLinkRequest>
     */
    public function staged(): array
    {
        return $this->stagedSource === null ? [] : ($this->stagedSource)();
    }
}
