<?php

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Material;

use Bga\Games\ProjectEdgeworth\Material\ActionCards;
use Bga\Games\ProjectEdgeworth\Material\ActionKind;
use PHPUnit\Framework\TestCase;

final class ActionCardsTest extends TestCase
{
    public function testHas5CardsInOrder(): void
    {
        $cards = ActionCards::all();
        self::assertSame(['A001', 'A002', 'A003', 'A004', 'A005'], array_keys($cards));
        self::assertSame(
            [ActionKind::Budget, ActionKind::Deploy, ActionKind::LaunchTurn, ActionKind::Link, ActionKind::Proposal],
            array_map(static fn ($c): ActionKind => $c->kind, array_values($cards)),
        );
        self::assertSame(['I', 'II', 'III', 'IV', 'V'], array_map(static fn ($c): string => $c->numeral, array_values($cards)));
    }

    public function testSpotChecksAgainstSpec002(): void
    {
        $a003 = ActionCards::get('A003');
        self::assertSame('打ち上げ / 転回', $a003->nameJa);
        self::assertSame('Launch / Turn', $a003->nameEn);
        self::assertSame('launch_turn', $a003->nameId);
        self::assertStringStartsWith("Choose one of the following.\nLaunch: ", $a003->mainEn);
        self::assertSame("Choose one of the following.\ni) Choose 1 active satellite and turn it.\nii) +1 [Budget].", $a003->bonusEn);

        self::assertSame('+6[マヤ]。', ActionCards::get('A001')->bonusJa);
        self::assertSame('+6 [Maya].', ActionCards::get('A001')->bonusEn);
    }
}
