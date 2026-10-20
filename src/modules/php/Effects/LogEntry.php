<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects;

/**
 * 窓の最中に溜める結果のログ 1 件。通知の組み立て（010 の N27〜N29 など）は窓を閉じる側が行う。
 */
final readonly class LogEntry
{
    /**
     * @param array<string, mixed> $args
     */
    public function __construct(
        public string $key,
        public array $args = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'args' => $this->args];
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function fromArray(array $a): self
    {
        return new self((string) $a['key'], $a['args']);
    }
}
