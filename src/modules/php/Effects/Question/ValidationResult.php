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
        /** @var array<string, mixed> */
        public array $args = [],
    ) {
    }

    public static function ok(): self
    {
        return new self(true, '');
    }

    /**
     * @param array<string, mixed> $args 文言の `${name}` に入れる値
     */
    public static function error(string $message, array $args = []): self
    {
        return new self(false, $message, $args);
    }
}
