<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Question;

final readonly class ValidationResult
{
    private function __construct(
        public bool $ok,
        public string $message,
    ) {
    }

    public static function ok(): self
    {
        return new self(true, '');
    }

    public static function error(string $message): self
    {
        return new self(false, $message);
    }
}
