<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Effects\Parts;

use Bga\Games\ProjectEdgeworth\Effects\Question\Answer;
use Bga\Games\ProjectEdgeworth\Effects\Question\ValidationResult;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;

/**
 * 結果部品の既定の振る舞い（1 段・いつでも使える・選択なし・検証は通る・zombie は辞退・見込みなし）。
 * 部品はこれを継承して、`apply()` と必要なものだけ上書きする。
 */
abstract class AbstractResultPart implements ResultPart
{
    public function stages(EffectSpec $spec): int
    {
        return $spec->stages;
    }

    public function isAvailable(PartCall $call): bool
    {
        return true;
    }

    public function acceptsSale(PartCall $call): bool
    {
        return false;
    }

    public function buildFields(PartCall $call): array
    {
        return [];
    }

    public function validate(PartCall $call, Answer $answer): ValidationResult
    {
        return ValidationResult::ok();
    }

    public function defaultAnswer(PartCall $call): ?Answer
    {
        return null;
    }

    public function preview(PartCall $call): array
    {
        return [];
    }
}
