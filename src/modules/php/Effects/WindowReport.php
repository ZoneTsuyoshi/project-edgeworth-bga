<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects;

use Bga\Games\ProjectEdgeworth\Effects\Context\StagedLinkRequest;

/**
 * 窓を開いた直後（30）・バリア（32）の報告。
 */
final readonly class WindowReport
{
    /**
     * @param list<int> $playersNeedingInput 問い（`ORDER` / `EFFECT`）に答える必要のあるプレイヤー（31 のアクティブ）
     * @param list<StagedLinkRequest> $stagedLinks バリアでコミットする、ステージされた通信確立（取り出し済み。007 §7-3）
     * @param bool $hadEffects この窓でトリガーされた効果が 1 件でもあったか（なければ窓は開かなかったものとして戻り先へ進む）
     */
    public function __construct(
        public array $playersNeedingInput,
        public array $stagedLinks,
        public bool $hadEffects,
        public bool $aborted,
    ) {
    }

    /** 31 へ行く（誰かの答えを待つ）か */
    public function needsInput(): bool
    {
        return $this->playersNeedingInput !== [];
    }

    /** 窓を閉じてよい（誰も待たず、コミットするステージもない）か */
    public function canClose(): bool
    {
        return $this->playersNeedingInput === [] && $this->stagedLinks === [];
    }
}
