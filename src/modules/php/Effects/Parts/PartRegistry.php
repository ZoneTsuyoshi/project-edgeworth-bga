<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Effects\Parts;

use Bga\Games\projectedgeworth\Material\PartCategory;
use Bga\Games\projectedgeworth\Material\PartId;

/**
 * `PartId` → 部品のインスタンス（011 §4-3）。未登録の部品を引くと例外（実装前のカードの効果を黙って無視しない）。
 */
final class PartRegistry
{
    /** @var array<string, object> */
    private array $parts = [];

    public function register(PartId $id, ResultPart|ModifierPart|ForbidPart $part): self
    {
        $expected = match ($id->category()) {
            PartCategory::Result => $part instanceof ResultPart,
            PartCategory::Modifier => $part instanceof ModifierPart,
            PartCategory::Forbid => $part instanceof ForbidPart,
        };
        if (!$expected) {
            throw new \InvalidArgumentException("part {$id->value} needs a {$id->category()->name} part");
        }
        $this->parts[$id->value] = $part;

        return $this;
    }

    public function has(PartId $id): bool
    {
        return isset($this->parts[$id->value]);
    }

    public function result(PartId $id): ResultPart
    {
        $part = $this->parts[$id->value] ?? throw new \LogicException("part {$id->value} is not implemented yet");

        return $part instanceof ResultPart ? $part : throw new \LogicException("part {$id->value} is not a result part");
    }

    public function modifier(PartId $id): ModifierPart
    {
        $part = $this->parts[$id->value] ?? throw new \LogicException("part {$id->value} is not implemented yet");

        return $part instanceof ModifierPart ? $part : throw new \LogicException("part {$id->value} is not a modifier part");
    }

    public function forbid(PartId $id): ForbidPart
    {
        $part = $this->parts[$id->value] ?? throw new \LogicException("part {$id->value} is not implemented yet");

        return $part instanceof ForbidPart ? $part : throw new \LogicException("part {$id->value} is not a forbid part");
    }
}
