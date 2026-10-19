<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Support\Effects;

use Bga\Games\ProjectEdgeworth\Effects\Outcome;
use Bga\Games\ProjectEdgeworth\Effects\Parts\AbstractResultPart;
use Bga\Games\ProjectEdgeworth\Effects\Parts\PartCall;
use Bga\Games\ProjectEdgeworth\Effects\Question\Answer;
use Bga\Games\ProjectEdgeworth\Effects\Question\ValidationResult;
use Bga\Games\ProjectEdgeworth\Material\EffectSpec;

/**
 * クロージャで振る舞いを与えられる結果部品（テスト用）。呼ばれた記録を `$applied` に残す。
 */
final class ScriptedResultPart extends AbstractResultPart
{
    /** @var list<array{string, int, ?Answer}> 適用の記録 [cardKey, stage, answer] */
    public array $applied = [];

    public function __construct(
        private readonly ?\Closure $onApply = null,
        private readonly ?\Closure $onFields = null,
        private readonly ?\Closure $onAvailable = null,
        private readonly ?\Closure $onDefault = null,
        private readonly ?\Closure $onValidate = null,
        private readonly ?int $stageCount = null,
    ) {
    }

    public function stages(EffectSpec $spec): int
    {
        return $this->stageCount ?? $spec->stages;
    }

    public function isAvailable(PartCall $call): bool
    {
        return $this->onAvailable === null ? true : (bool) ($this->onAvailable)($call);
    }

    public function buildFields(PartCall $call): array
    {
        return $this->onFields === null ? [] : ($this->onFields)($call);
    }

    public function validate(PartCall $call, Answer $answer): ValidationResult
    {
        return $this->onValidate === null ? ValidationResult::ok() : ($this->onValidate)($call, $answer);
    }

    public function apply(PartCall $call, ?Answer $answer): Outcome
    {
        $this->applied[] = [$call->spec->cardKey, $call->stage, $answer];

        return $this->onApply === null ? Outcome::none() : ($this->onApply)($call, $answer);
    }

    public function defaultAnswer(PartCall $call): ?Answer
    {
        return $this->onDefault === null ? null : ($this->onDefault)($call);
    }
}
