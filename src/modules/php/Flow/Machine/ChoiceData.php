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
     * @param list<string>|null $useMods 使う起動の修正の宿主の鍵
     * @return array<string, mixed>
     */
    public static function deploy(string $op, int $cardId, int $satId, ?array $sell, ?array $useMods = null): array
    {
        return match ($op) {
            'deploy' => ['op' => 'deploy', 'card' => $cardId, 'sat' => $satId] + self::sell($sell) + self::mods($useMods),
            'draw', 'pass' => ['op' => $op],
            default => ['op' => $op],
        };
    }

    /**
     * @param list<int>|null $sell
     * @param list<string>|null $useMods
     * @return array<string, mixed>
     */
    public static function launch(string $op, int $satId, string $dir, ?array $sell, ?array $useMods = null): array
    {
        return match ($op) {
            'launch' => ['op' => 'launch', 'sat' => $satId, 'dir' => $dir] + self::sell($sell) + self::mods($useMods),
            'turn' => ['op' => 'turn', 'sat' => $satId, 'dir' => $dir],
            default => ['op' => $op],
        };
    }

    /**
     * @param list<int>|null $sell
     * @param list<string>|null $useMods
     * @return array<string, mixed>
     */
    public static function link(string $op, int $cell, int $satId, ?array $sell, ?array $useMods = null): array
    {
        return $op === 'link'
            ? ['op' => 'link', 'cell' => $cell, 'sat' => $satId] + self::sell($sell) + self::mods($useMods)
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
     * 企画の確定（残すカード、手札から捨てるカード、オプションの使用、売却）。
     *
     * @param list<int>|null $keep
     * @param list<int>|null $handDiscard
     * @param array<mixed>|null $optionUses 宿主の鍵 => 値
     * @param list<int>|null $sell
     * @return array<string, mixed>
     */
    public static function keep(?array $keep, ?array $handDiscard, ?array $optionUses, ?array $sell): array
    {
        $data = ['keep' => self::ids($keep)];
        if (self::ids($handDiscard) !== []) {
            $data['handDiscard'] = self::ids($handDiscard);
        }
        if ($optionUses !== null && $optionUses !== []) {
            $data['optionUses'] = $optionUses;
        }

        return $data + self::sell($sell);
    }

    /**
     * 起動の修正（宿主の鍵の配列。USE_MODS）。空なら載せない。
     *
     * @param list<string>|null $useMods
     * @return array{useMods?: list<string>}
     */
    private static function mods(?array $useMods): array
    {
        $keys = array_map('strval', $useMods ?? []);

        return $keys === [] ? [] : ['useMods' => $keys];
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
