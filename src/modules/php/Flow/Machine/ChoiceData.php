<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Flow\Machine;

/**
 * act* の引数から choice_data（010 §9.3）を組み立てる。検証はルール層（ActionStep::validate）が確定のたびに行うので、
 * ここは形を整えるだけ（空配列は送信で落ちて null になることがあるので null を空配列として扱う）。
 */
final class ChoiceData
{
    private function __construct()
    {
    }

    /**
     * @param list<int>|null $sell
     * @return array<string, mixed>
     */
    public static function deploy(string $op, int $cardId, int $satId, ?array $sell): array
    {
        return match ($op) {
            'deploy' => ['op' => 'deploy', 'card' => $cardId, 'sat' => $satId] + self::sell($sell),
            'draw', 'pass' => ['op' => $op],
            default => ['op' => $op],
        };
    }

    /**
     * @param list<int>|null $sell
     * @return array<string, mixed>
     */
    public static function launch(string $op, int $satId, string $dir, ?array $sell): array
    {
        return match ($op) {
            'launch' => ['op' => 'launch', 'sat' => $satId, 'dir' => $dir] + self::sell($sell),
            'turn' => ['op' => 'turn', 'sat' => $satId, 'dir' => $dir],
            default => ['op' => $op],
        };
    }

    /**
     * @param list<int>|null $sell
     * @return array<string, mixed>
     */
    public static function link(string $op, int $cell, int $satId, ?array $sell): array
    {
        return $op === 'link'
            ? ['op' => 'link', 'cell' => $cell, 'sat' => $satId] + self::sell($sell)
            : ['op' => $op];
    }

    /**
     * @param array<mixed>|null $ids
     * @return list<int>
     */
    public static function ids(?array $ids): array
    {
        return array_values(array_map('intval', $ids ?? []));
    }

    /**
     * @param list<int>|null $sell
     * @return array{sell?: list<int>}
     */
    private static function sell(?array $sell): array
    {
        $ids = self::ids($sell);
        return $ids === [] ? [] : ['sell' => $ids];
    }
}
