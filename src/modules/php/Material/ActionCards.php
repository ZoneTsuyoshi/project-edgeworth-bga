<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\ProjectEdgeworth\Material;

/**
 * アクションカード 5 枚（A001〜A005）の定義（002 §9）。文面は CardTexts::ACTION。
 */
final class ActionCards
{
    /** @var array<string, array{string, string, string, string, string}> */
    private const ROWS = [
        'A001' => ['I', '予算発行', 'Budget', 'budget', 'BUDGET'],
        'A002' => ['II', '配備', 'Deploy', 'deploy', 'DEPLOY'],
        'A003' => ['III', '打ち上げ / 転回', 'Launch / Turn', 'launch_turn', 'LAUNCH_TURN'],
        'A004' => ['IV', '通信確立', 'Establish Link', 'establish_link', 'LINK'],
        'A005' => ['V', '企画', 'Proposal', 'proposal', 'PROPOSAL'],
    ];

    /** @var array<string, ActionCardDefinition>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, ActionCardDefinition> カード ID => 定義（ID 順）
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            $cards = [];
            foreach (self::ROWS as $id => $row) {
                $cards[$id] = new ActionCardDefinition(
                    $id,
                    $row[0],
                    $row[1],
                    $row[2],
                    $row[3],
                    ActionKind::from($row[4]),
                    CardTexts::ACTION[$id]['mainJa'],
                    CardTexts::ACTION[$id]['mainEn'],
                    CardTexts::ACTION[$id]['bonusJa'],
                    CardTexts::ACTION[$id]['bonusEn'],
                );
            }
            self::$cache = $cards;
        }

        return self::$cache;
    }

    public static function get(string $id): ActionCardDefinition
    {
        return self::all()[$id] ?? throw new \OutOfBoundsException("未定義のカード ID: {$id}");
    }
}
