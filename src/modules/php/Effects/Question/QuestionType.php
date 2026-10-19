<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Question;

enum QuestionType: string
{
    /** 衝突する効果の順序を選ぶ（007 §6） */
    case Order = 'ORDER';
    /** 効果を使うか・中身の選択（007 §6-3 の手順 4） */
    case Effect = 'EFFECT';
}
