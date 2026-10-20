<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace ProjectEdgeworthTests\Unit\Effects;

use Bga\Games\projectedgeworth\Effects\Pool\DefaultOrder;
use Bga\Games\projectedgeworth\Material\SatelliteShape;
use ProjectEdgeworthTests\Support\Effects\FakeGame;

/**
 * デフォルトの順（007 §6-3、Q-15）。
 */
final class DefaultOrderTest extends EffectsTestCase
{
    public function testShapeOrderThenSatelliteCardThenEarliestDeployedCard(): void
    {
        $game = (new FakeGame())->player(1);
        $stealth = $game->satellite(1, SatelliteShape::Stealth);
        $hammer = $game->satellite(1, SatelliteShape::Hammer);
        $game->deploy($stealth, 'P013');
        $game->deploy($hammer, 'P023')->deploy($hammer, 'P027');

        $items = [];
        $hosts = [];
        foreach ([['P', 13], ['P', 27], ['S', $hammer], ['P', 23], ['S', $stealth]] as $i => [$kind, $src]) {
            $host = $game->host($kind, $src) ?? throw new \LogicException('no host');
            $items[] = $this->item($i + 1, $kind, $src, $host->cardKey);
            $hosts[$i + 1] = $host;
        }

        $sorted = array_map(static fn($i): string => $i->hostKey(), DefaultOrder::sort($items, $hosts));

        // HAMMER（衛星カード → P023 → P027）→ STEALTH（衛星カード → P013）
        $this->assertSame(['S:' . $hammer, 'P:23', 'P:27', 'S:' . $stealth, 'P:13'], $sorted);
    }

    public function testEffectsWithoutASatelliteComeLast(): void
    {
        $game = (new FakeGame())->player(1);
        $sat = $game->satellite(1, SatelliteShape::Stealth);
        $host = $game->host('S', $sat) ?? throw new \LogicException('no host');
        $items = [$this->item(1, 'A', 99, 'action_card'), $this->item(2, 'S', $sat, $host->cardKey)];

        $sorted = DefaultOrder::sort($items, [2 => $host]);

        $this->assertSame([2, 1], array_map(static fn($i): int => $i->id, $sorted));
    }
}
